import os
import re
import sys

def find_menu_slugs(plugin_dir):
    slugs = set()
    # Expanded list of functions that create admin menu pages
    menu_functions = [
        'add_menu_page', 'add_submenu_page', 'add_options_page',
        'add_dashboard_page', 'add_posts_page', 'add_media_page',
        'add_pages_page', 'add_comments_page', 'add_theme_page',
        'add_plugins_page', 'add_users_page', 'add_management_page'
    ]
    # Regex to find the menu slug (typically the 4th argument)
    pattern = re.compile(r"(?:%s)\s*\((?:[^,]+,){3}\s*'([^']+)'" % '|'.join(menu_functions))

    for root, _, files in os.walk(plugin_dir):
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(root, file)
                try:
                    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                        content = f.read()
                        matches = pattern.findall(content)
                        for slug in matches:
                            slugs.add(slug)
                except Exception:
                    pass
    return list(slugs)

def find_slug_usage(plugin_dir, slug):
    print(f"--- Grepping for slug: '{slug}' ---")
    for root, _, files in os.walk(plugin_dir):
        for file in files:
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                    for i, line in enumerate(f, 1):
                        if slug in line:
                            print(f"{filepath}:{i}:{line.strip()}")
            except Exception:
                pass
    print("")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python find_wrapper_functions.py <plugin_directory>")
        sys.exit(1)

    plugin_dir = sys.argv[1]

    if not os.path.isdir(plugin_dir):
        print(f"Error: Directory not found at '{plugin_dir}'")
        sys.exit(1)

    print(f"Searching for menu slugs in {plugin_dir}...")
    slugs = find_menu_slugs(plugin_dir)

    if not slugs:
        print("No menu slugs found.")
        sys.exit(0)

    print(f"Found slugs: {slugs}")
    print("")

    for slug in slugs:
        find_slug_usage(plugin_dir, slug)