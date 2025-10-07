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
    pattern = re.compile(r"(?P<function>add_(?:menu|submenu|options|dashboard|posts|media|pages|comments|theme|plugins|users|management)_page)\\s*\\((?P<args>.*?)\\);", re.DOTALL)

    for root, _, files in os.walk(plugin_dir):
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(root, file)
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
    return definitions

def analyze_definitions(definitions, nginx_whitelist):
    results = []
    for definition in definitions:
        slug = definition['menu_slug']
        url = f"admin.php?page={slug}"
        if definition['function'] == 'add_options_page':
            url = f"options-general.php?page={slug}"

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
    if len(sys.argv) < 2:
        print("Usage: python analyze_plugins.py <plugin_directory>")
        sys.exit(1)

    plugin_dir = sys.argv[1]

    if not os.path.isdir(plugin_dir):
        print(f"Error: Directory not found at '{plugin_dir}'")
        sys.exit(1)

    # Whitelist from the hardened nginx config
    nginx_whitelist = [
        'admin.php',
        'admin-ajax.php',
        'admin-post.php',
        'options-general.php', # I added this
    ]

    print(f"Analyzing plugin in {plugin_dir}...")
    definitions = find_menu_definitions(plugin_dir)
    results = analyze_definitions(definitions, nginx_whitelist)

    print(json.dumps(results, indent=4))
