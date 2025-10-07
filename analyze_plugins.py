import os
import re
import sys
import json

def find_menu_definitions(plugin_dir):
    definitions = []
    menu_functions = [
        'add_menu_page', 'add_submenu_page', 'add_options_page',
        'add_dashboard_page', 'add_posts_page', 'add_media_page',
        'add_pages_page', 'add_comments_page', 'add_theme_page',
        'add_plugins_page', 'add_users_page', 'add_management_page'
    ]
    pattern = re.compile(r"(?P<function>add_(?:menu|submenu|options|dashboard|posts|media|pages|comments|theme|plugins|users|management)_page)\s*\((?P<args>.*?)\);", re.DOTALL)

    for root, _, files in os.walk(plugin_dir):
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(root, file)
                try:
                    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                        content = f.read()
                        for match in pattern.finditer(content):
                            args_str = match.group('args')
                            # This is a simplified parser, it will not handle all cases
                            args = [arg.strip().strip("'\"") for arg in args_str.split(',')]
                            if len(args) >= 4:
                                definition = {
                                    'function': match.group('function'),
                                    'page_title': args[0],
                                    'menu_title': args[1],
                                    'capability': args[2],
                                    'menu_slug': args[3],
                                    'file': filepath,
                                    'line': content.count('\n', 0, match.start()) + 1
                                }
                                if len(args) >= 5:
                                    definition['callback'] = args[4]
                                definitions.append(definition)
                except Exception:
                    pass
    return definitions

def analyze_definitions(definitions, nginx_whitelist):
    results = []
    for definition in definitions:
        slug = definition['menu_slug']
        url = f"admin.php?page={slug}"
        if definition['function'] == 'add_options_page':
            url = f"options-general.php?page={slug}"
        elif definition['function'] == 'add_dashboard_page':
            url = f"index.php?page={slug}"
        elif definition['function'] == 'add_posts_page':
            url = f"edit.php?page={slug}"
        elif definition['function'] == 'add_media_page':
            url = f"upload.php?page={slug}"
        elif definition['function'] == 'add_pages_page':
            url = f"edit.php?post_type=page&page={slug}"
        elif definition['function'] == 'add_comments_page':
            url = f"edit-comments.php?page={slug}"
        elif definition['function'] == 'add_theme_page':
            url = f"themes.php?page={slug}"
        elif definition['function'] == 'add_plugins_page':
            url = f"plugins.php?page={slug}"
        elif definition['function'] == 'add_users_page':
            url = f"users.php?page={slug}"
        elif definition['function'] == 'add_management_page':
            url = f"tools.php?page={slug}"


        is_allowed = False
        for allowed_file in nginx_whitelist:
            if allowed_file in url:
                is_allowed = True
                break

        definition['url'] = url
        definition['is_allowed'] = is_allowed
        results.append(definition)
    return results

def read_nginx_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            return f.read()
    except FileNotFoundError:
        print(f"Error: Nginx config file not found at '{filepath}'", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Error reading Nginx config file '{filepath}': {e}", file=sys.stderr)
        sys.exit(1)

def extract_nginx_params(config_content):
    params = {}
    # Extract server_name
    match = re.search(r"server_name\s+(?P<server_name>[^;]+);", config_content)
    if match:
        params['server_name'] = match.group('server_name').strip()

    # Extract root
    match = re.search(r"root\s+(?P<root>[^;]+);", config_content)
    if match:
        params['root'] = match.group('root').strip()

    # Extract log files
    match = re.search(r"access_log\s+(?P<access_log>[^;]+);", config_content)
    if match:
        params['access_log'] = match.group('access_log').strip()
    match = re.search(r"error_log\s+(?P<error_log>[^;]+);", config_content)
    if match:
        params['error_log'] = match.group('error_log').strip()

    # Extract uwsgi_pass directive
    match = re.search(r"location\s+~\s+\.php\$\s*{\s*include\s+uwsgi_params;\s*uwsgi_modifier1\s+(?P<modifier>\d+);\s*uwsgi_pass\s+(?P<pass_target>[^;]+);", config_content, re.DOTALL)
    if match:
        params['uwsgi_modifier1'] = match.group('modifier')
        params['uwsgi_pass_target'] = match.group('pass_target').strip()

    return params

def generate_hardened_nginx_config(nginx_params, ssl_conf_content, wp_restrictions_conf_content, wp_main_conf_content, additional_whitelisted_admin_files):
    server_name = nginx_params.get('server_name', 'your_domain.com')
    root = nginx_params.get('root', '/var/www/wordpress')
    access_log = nginx_params.get('access_log', '/var/log/nginx/access.log')
    error_log = nginx_params.get('error_log', '/var/log/nginx/error.log')
    uwsgi_modifier1 = nginx_params.get('uwsgi_modifier1', '14')
    uwsgi_pass_target = nginx_params.get('uwsgi_pass_target', 'unix:/run/uwsgi/your_site.socket')

    # Clean up included contents to avoid duplication and integrate
    # Remove location blocks from wp_main_conf_content that will be explicitly defined
    wp_main_conf_content_cleaned = re.sub(r"location\s+/\s*{\s*.*?try_files\s+\\$uri\s+\\$uri/\s+/index.php\?\\$args;.*?\}", "", wp_main_conf_content, flags=re.DOTALL)
    wp_main_conf_content_cleaned = re.sub(r"location\s+~*\s+\.\(ogg|ogv|svg|svgz|eot|otf|woff|mp4|ttf|rss|atom|jpg|jpeg|gif|png|ico|zip|tgz|gz|rar|bz2|doc|xls|exe|ppt|tar|mid|midi|wav|bmp|rtf\)\\\s*{\s*.*?expires\s+max;.*?\}", "", wp_main_conf_content_cleaned, flags=re.DOTALL)
    wp_main_conf_content_cleaned = re.sub(r"rewrite\s+/wp-admin\$\s+\\$scheme://\\$host\\$uri/\s+permanent;", "", wp_main_conf_content_cleaned)

    # Remove location blocks from wp_restrictions_conf_content that will be explicitly defined
    wp_restrictions_conf_content_cleaned = re.sub(r"location\s+=\s+/favicon.ico\s*{\s*.*?\}", "", wp_restrictions_conf_content, flags=re.DOTALL)
    wp_restrictions_conf_content_cleaned = re.sub(r"location\s+=\s+/robots.txt\s*{\s*.*?\}", "", wp_restrictions_conf_content_cleaned, flags=re.DOTALL)
    wp_restrictions_conf_content_cleaned = re.sub(r"location\s+~\s+/\\.ht\s*{\s*.*?\}", "", wp_restrictions_conf_content_cleaned, flags=re.DOTALL)
    wp_restrictions_conf_content_cleaned = re.sub(r"location\s+~*\s+/(?:uploads|files)/.*\\.php\$\\s*{\s*.*?\}", "", wp_restrictions_conf_content_cleaned, flags=re.DOTALL)
    wp_restrictions_conf_content_cleaned = re.sub(r"location\s+=\s+/xmlrpc.php\s*{\s*.*?\}", "", wp_restrictions_conf_content_cleaned, flags=re.DOTALL)


    config = f"""# Full Hardened Nginx Configuration for {server_name}
# Generated by analyze_plugins.py

# HTTP to HTTPS redirect
server {{
    listen 80;
    server_name {server_name};
    return 301 https://{server_name}$request_uri;

    access_log {access_log};
    error_log {error_log};
}}

server {{
    listen 443 ssl http2;
    server_name {server_name};

    root {root};

    # SSL configuration (from ssl.conf)
{ssl_conf_content}

    access_log {access_log};
    error_log {error_log};

    # Main router for WordPress pretty URLs
    location / {{
        index       index.php;
        try_files $uri $uri/ /index.php?$args;
    }}

    # Add trailing slash to */wp-admin requests.
    rewrite /wp-admin$ $scheme://$host$uri/ permanent;

    # Directives to send expires headers and turn off 404 error logging.
    location ~* ^.+\.(ogg|ogv|svg|svgz|eot|otf|woff|mp4|ttf|rss|atom|jpg|jpeg|gif|png|ico|svg|webp)$ {{
        access_log off;
        log_not_found off;
        expires max;
    }}

    # ====================================================================
    # SECURITY: Deny access to sensitive files and directories
    # (from wp-restrictions.conf and hardened template)
    # ====================================================================
    location = /favicon.ico {{
        log_not_found off;
        access_log off;
    }}

    location = /robots.txt {{
        allow all;
        log_not_found off;
        access_log off;
    }}

    location ~ /\.ht {{
        deny all;
    }}

    location = /wp-config.php {{ deny all; }}
    location = /xmlrpc.php {{ deny all; }}
    location ~* \\.(engine|inc|info|install|make|module|profile|test|po|sh|sql|theme|tpl(\\..+)?|xtmpl)\\$|^(\\..*|Entries.*|Repository|Root|Tag|Template)\\$|\\~\\$ {{
        deny all;
    }}
    location ~* /(?:uploads|files)/.*\\.php\$ {{
        deny all;
    }}

    # ====================================================================
    # PHP ALLOWLIST: Explicitly allow only necessary WordPress PHP files
    # ====================================================================

    # --- Root Files ---
    location = /index.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-login.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-cron.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-signup.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-activate.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}

    # --- wp-admin Files ---
    location = /wp-admin/admin-ajax.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/admin-post.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    # Whitelisted for plugins
    location = /wp-admin/options-general.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/tools.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/admin.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/edit.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/upload.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/edit-comments.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/themes.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/plugins.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}
    location = /wp-admin/users.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}


    # --- wp-includes Files (Rarely Needed) ---
    location = /wp-includes/js/tinymce/wp-tinymce.php {{ include uwsgi_params; uwsgi_modifier1 {uwsgi_modifier1}; uwsgi_pass {uwsgi_pass_target}; }}

    # ====================================================================
    # FINAL CATCH-ALL: Deny any other PHP file request.
    # ====================================================================
    location ~ \.php$ {{
        deny all;
    }}

    # Standard location block for static assets
    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|webp)\\$ {{
        expires max;
        log_not_found off;
    }}
}}
"""
    return config

if __name__ == "__main__":
    base_path = "/srv/www/psyche.ffzg.hr/"
    prefix = "/mnt/ws2/"

    target_path = base_path
    if len(sys.argv) > 1:
        target_path = sys.argv[1]

    if not os.path.isdir(target_path):
        if os.path.isdir(prefix + target_path):
            target_path = prefix + target_path
        else:
            print(f"Error: Directory not found at '{target_path}'", file=sys.stderr)
            sys.exit(1)

    plugins_dir = os.path.join(target_path, "wp-content", "plugins")

    if not os.path.isdir(plugins_dir):
        print(f"Error: Plugins directory not found at '{plugins_dir}'", file=sys.stderr)
        sys.exit(1)

    # Read original Nginx config files
    main_site_conf_path = "/mnt/ws2/etc/nginx/sites-available/psyche.ffzg.hr.conf"
    ssl_conf_path = "/mnt/ws2/etc/nginx/confs/ssl.conf"
    wp_restrictions_conf_path = "/mnt/ws2/etc/nginx/confs/wp-restrictions.conf"
    wp_main_conf_path = "/mnt/ws2/etc/nginx/confs/wp-main.conf"

    main_site_conf_content = read_nginx_config(main_site_conf_path)
    ssl_conf_content = read_nginx_config(ssl_conf_path)
    wp_restrictions_conf_content = read_nginx_config(wp_restrictions_conf_path)
    wp_main_conf_content = read_nginx_config(wp_main_conf_path)

    nginx_params = extract_nginx_params(main_site_conf_content)

    # Whitelist from the hardened nginx config
    nginx_whitelist = [
        'admin.php',
        'admin-ajax.php',
        'admin-post.php',
        'options-general.php',
        'tools.php',
        'index.php', # for add_dashboard_page
        'edit.php', # for add_posts_page, add_pages_page
        'upload.php', # for add_media_page
        'edit-comments.php', # for add_comments_page
        'themes.php', # for add_theme_page
        'plugins.php', # for add_plugins_page
        'users.php', # for add_users_page
    ]

    all_plugins_results = {}

    for plugin_name in os.listdir(plugins_dir):
        plugin_path = os.path.join(plugins_dir, plugin_name)
        if os.path.isdir(plugin_path):
            # print(f"Analyzing plugin: {plugin_name}...", file=sys.stderr) # Commented out for cleaner STDOUT
            definitions = find_menu_definitions(plugin_path)
            results = analyze_definitions(definitions, nginx_whitelist)
            all_plugins_results[plugin_name] = results

    # Generate and print the full hardened configuration
    generated_config = generate_hardened_nginx_config(
        nginx_params,
        ssl_conf_content,
        wp_restrictions_conf_content,
        wp_main_conf_content,
        nginx_whitelist # Pass the whitelist for dynamic whitelisting if needed
    )
    print(generated_config)