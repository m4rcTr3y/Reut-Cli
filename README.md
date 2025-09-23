# MyFramework CLI Tool

## Requirements
- PHP (>=7.4) and Composer in system PATH.

## Installation
1. **Install PHP**:
   - Linux: `sudo apt-get install php-cli`
   - macOS: `brew install php`
   - Windows: Download from https://www.php.net/downloads, extract to `C:\php`, add to PATH.
2. **Install Composer**: https://getcomposer.org/download/
3. **Global CLI**:
   - Linux/macOS: `sudo cp myframework /usr/local/bin/ && sudo chmod +x /usr/local/bin/myframework`
   - Windows: Copy `myframework` and `myframework.bat` to `C:\Program Files\myframework`, add to PATH.

## Usage
- Initialize: `myframework init` (interactive project setup).
- Run manage.php commands: `myframework manage.php generate:model Users` (from project dir).
- Or use: `php manage.php generate:model Users` inside the project.

## Setup
- Place your `config/` directory in `myframework/templates/config/`.
- After `init`, run `composer install` in the project directory.