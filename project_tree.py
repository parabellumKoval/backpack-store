import os
import argparse
import fnmatch
from pathlib import Path

import re


# ====== Настройки для разных типов проектов ======
PROJECT_CONFIGS = {
    "default": {
        "max_depth": 5,
        "ignore_dirs": [".git", "node_modules", "vendor", ".cache", "__pycache__"],
        "include_files": ["Dockerfile", ".env", ".env.*", "composer.json", "package.json", "*.config.ts"],
    },
    "laravel": {
        "max_depth": 4,
        "ignore_dirs": [".git", "node_modules", "vendor", "storage", "tests"],
        "include_files": ["composer.json", ".env", "artisan", "webpack.mix.js"],
    },
    "nuxt": {
        "max_depth": 4,
        "ignore_dirs": [".git", ".nuxt", "node_modules", ".output"],
        "include_files": ["nuxt.config.ts", "nuxt.config.js", "package.json", ".env"],
    },
    "docker": {
        "max_depth": 3,
        "ignore_dirs": [".git", ".cache"],
        "include_files": ["Dockerfile", "docker-compose.yml", ".env"],
    },
    # Добавляй свои конфигурации тут
}

def parse_gitignore(root_path):
    gitignore_path = os.path.join(root_path, '.gitignore')
    if not os.path.exists(gitignore_path):
        return []

    with open(gitignore_path, 'r') as f:
        lines = f.read().splitlines()

    patterns = []
    for line in lines:
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        # Преобразуем шаблон .gitignore в glob-совместимый
        if line.endswith('/'):
            line = line[:-1]
        patterns.append(line)

    return patterns


def should_ignore(path, ignore_patterns, base_path):
    rel_path = os.path.relpath(path, base_path)
    for pattern in ignore_patterns:
        if fnmatch.fnmatch(rel_path, pattern) or fnmatch.fnmatch(os.path.basename(path), pattern):
            return True
    return False


# Изменено определение print_tree()
def print_tree(path, config, ignore_patterns, depth=0, max_depth=3, show_files=False):
    if depth > max_depth:
        return ""

    output = ""
    items = sorted(os.listdir(path))
    for item in items:
        full_path = os.path.join(path, item)

        if should_ignore(full_path, config['ignore_dirs'] + ignore_patterns, path):
            continue

        if os.path.isdir(full_path):
            output += "  " * depth + f"{item}/\n"
            output += print_tree(full_path, config, ignore_patterns, depth + 1, max_depth, show_files)
        else:
            # Показываем файл только если:
            # 1. Включен флаг show_files, ИЛИ
            # 2. Он явно включён через include_files
            if (
                show_files or
                any(fnmatch.fnmatch(item, pat) for pat in config['include_files'])
            ):
                output += "  " * depth + item + "\n"

    return output



def main():
    parser = argparse.ArgumentParser(description="Project structure printer for AI context.")
    parser.add_argument("path", help="Root project directory (absolute or relative)")
    parser.add_argument("--project-type", default="default", help="Project type: laravel, nuxt, docker etc.")
    parser.add_argument("--show-files", action="store_true", help="Show regular files (e.g. .php) in tree")

    args = parser.parse_args()
    root_path = os.path.abspath(args.path)
    project_type = args.project_type.lower()

    config = PROJECT_CONFIGS.get(project_type, PROJECT_CONFIGS["default"])
    ignore_patterns = parse_gitignore(root_path)

    print(f"📂 Project structure for '{project_type}' at '{root_path}':\n")
    tree_output = print_tree(root_path, config, ignore_patterns, max_depth=config["max_depth"])
    print(tree_output)


if __name__ == "__main__":
    main()
