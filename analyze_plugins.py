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
            print(f"Error: Directory not found at '{target_path}'")
            sys.exit(1)

    plugins_dir = os.path.join(target_path, "wp-content", "plugins")

    if not os.path.isdir(plugins_dir):
        print(f"Error: Plugins directory not found at '{plugins_dir}'")
        sys.exit(1)

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
            print(f"Analyzing plugin: {plugin_name}...")
            definitions = find_menu_definitions(plugin_path)
            results = analyze_definitions(definitions, nginx_whitelist)
            all_plugins_results[plugin_name] = results

    print(json.dumps(all_plugins_results, indent=4))