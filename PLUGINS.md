# REUT Framework - Plugins

REUT features a plugin system that allows you to extend the framework's functionality. Plugins are separate Composer packages that integrate seamlessly with REUT.

## Table of Contents

1. [Available Plugins](#available-plugins)
2. [Email Plugin](#email-plugin)
3. [Creating Your Own Plugin](#creating-your-own-plugin)

---

## Available Plugins

### Official Plugins

| Plugin | Package | Description | Version |
|--------|---------|-------------|---------|
| **Email** | `reut/email` | SMTP email sending functionality | Latest |

### Community Plugins

*No community plugins yet. Submit yours!*

---

## Email Plugin

The Email plugin provides SMTP email sending functionality for the REUT framework.

### Installation

Install the package via Composer:

```bash
composer require reut/email
```

### Requirements

- PHP >= 7.4
- REUT Core ^1.1
- PHPMailer ^6.9

### Configuration

Add the following environment variables to your `.env` file:

```env
REUT_EMAIL_ENABLED=true
REUT_EMAIL_REQUIRES_AUTH=false
REUT_EMAIL_SMTP_HOST=smtp.gmail.com
REUT_EMAIL_SMTP_PORT=587
REUT_EMAIL_SMTP_USERNAME=your-email@gmail.com
REUT_EMAIL_SMTP_PASSWORD=your-app-password
REUT_EMAIL_SMTP_ENCRYPTION=tls
REUT_EMAIL_FROM_ADDRESS=noreply@example.com
REUT_EMAIL_FROM_NAME=REUT Framework
```

### Configuration Options

| Option | Description | Default | Required |
|--------|-------------|---------|----------|
| `REUT_EMAIL_ENABLED` | Enable/disable email functionality | `false` | No |
| `REUT_EMAIL_REQUIRES_AUTH` | Require JWT authentication for email endpoints | `false` | No |
| `REUT_EMAIL_SMTP_HOST` | SMTP server hostname | - | Yes |
| `REUT_EMAIL_SMTP_PORT` | SMTP server port | `587` | No |
| `REUT_EMAIL_SMTP_USERNAME` | SMTP username | - | Optional |
| `REUT_EMAIL_SMTP_PASSWORD` | SMTP password | - | Optional |
| `REUT_EMAIL_SMTP_ENCRYPTION` | Encryption type: `tls`, `ssl`, or empty | `tls` | No |
| `REUT_EMAIL_FROM_ADDRESS` | Default from email address | - | Yes |
| `REUT_EMAIL_FROM_NAME` | Default from name | `REUT Framework` | No |

### Integration

Add the email router to your project's `routers/routes.php`:

```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\AuthRouter;
use Reut\Router\DocsController;
use Reut\Router\SchemaController;
use Reut\Router\ReuteRoute;
use Reut\Email\EmailRouter;
use Reut\Email\Config\EmailConfig;
use Slim\App;

return function (App $app, array $config): void {
    // ... existing routes ...

    // Register email routes
    if ((strtolower($_ENV['REUT_EMAIL_ENABLED'] ?? 'false')) === 'true') {
        $emailConfig = EmailConfig::load();
        if (EmailConfig::isEnabled($emailConfig)) {
            new EmailRouter($app, $config, $emailConfig);
        }
    }
};
```

### Authentication

By default, email endpoints are publicly accessible. To require JWT authentication for all email endpoints, set:

```env
REUT_EMAIL_REQUIRES_AUTH=true
```

When enabled, all requests to email endpoints must include a valid JWT token in the `Authorization` header:

```
Authorization: Bearer <your-jwt-token>
```

### API Endpoints

#### Send Email

**POST** `/email/send`

Send an email via SMTP.

**Authentication:** Optional (controlled by `REUT_EMAIL_REQUIRES_AUTH`)

**Request Body:**
```json
{
    "to": "recipient@example.com",
    "subject": "Hello from REUT",
    "body": "<h1>Hello</h1><p>This is a test email.</p>",
    "bodyType": "html",
    "cc": "cc@example.com",
    "bcc": ["bcc1@example.com", "bcc2@example.com"],
    "replyTo": "reply@example.com",
    "replyToName": "Reply Name"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `to` | string | Yes | Recipient email address |
| `subject` | string | Yes | Email subject |
| `body` | string | Yes | Email body (HTML or plain text) |
| `bodyType` | string | No | `html` or `text` (default: `html`) |
| `cc` | string/array | No | CC recipient(s) |
| `bcc` | string/array | No | BCC recipient(s) |
| `replyTo` | string | No | Reply-to email address |
| `replyToName` | string | No | Reply-to name |

**Response (Success):**
```json
{
    "success": true,
    "message": "Email sent successfully"
}
```

**Response (Error):**
```json
{
    "error": true,
    "message": "Validation failed",
    "errors": ["to field is required"]
}
```

#### Check Status

**GET** `/email/status`

Check email service status and configuration.

**Authentication:** Optional (controlled by `REUT_EMAIL_REQUIRES_AUTH`)

**Response:**
```json
{
    "status": "ok",
    "smtp_configured": true,
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "from_address": "noreply@example.com",
    "errors": []
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | `ok` or `error` |
| `smtp_configured` | boolean | Whether SMTP is properly configured |
| `smtp_host` | string | SMTP hostname |
| `smtp_port` | integer | SMTP port |
| `from_address` | string | Default from address |
| `errors` | array | List of configuration errors |

### Usage Examples

#### Basic Email Sending

```php
<?php

use Reut\Email\EmailService;
use Reut\Email\Config\EmailConfig;

$emailConfig = EmailConfig::load();
$emailService = new EmailService($emailConfig);

try {
    $emailService->send(
        'user@example.com',
        'Welcome!',
        '<h1>Welcome to our service</h1>',
        'html'
    );
    echo "Email sent successfully!";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

#### Email with CC and BCC

```php
$emailService->send(
    'user@example.com',
    'Invoice',
    '<p>Please find your invoice attached.</p>',
    'html',
    [
        'cc' => 'manager@example.com',
        'bcc' => ['archive@example.com', 'backup@example.com']
    ]
);
```

#### Email with Attachments

```php
$emailService->send(
    'user@example.com',
    'Invoice',
    '<p>Please find your invoice attached.</p>',
    'html',
    [
        'attachments' => [
            '/path/to/invoice.pdf',
            [
                'path' => '/path/to/receipt.pdf',
                'name' => 'receipt.pdf'
            ]
        ]
    ]
);
```

#### Plain Text Email

```php
$emailService->send(
    'user@example.com',
    'Welcome',
    'Welcome to our service!',
    'text'
);
```

#### Email with Reply-To

```php
$emailService->send(
    'user@example.com',
    'Support Request',
    '<p>Your support request has been received.</p>',
    'html',
    [
        'replyTo' => 'support@example.com',
        'replyToName' => 'Support Team'
    ]
);
```

### Common SMTP Providers

#### Gmail

```env
REUT_EMAIL_SMTP_HOST=smtp.gmail.com
REUT_EMAIL_SMTP_PORT=587
REUT_EMAIL_SMTP_ENCRYPTION=tls
REUT_EMAIL_SMTP_USERNAME=your-email@gmail.com
REUT_EMAIL_SMTP_PASSWORD=your-app-password
```

**Note:** Gmail requires an [App Password](https://support.google.com/accounts/answer/185833) for SMTP authentication.

#### Outlook/Hotmail

```env
REUT_EMAIL_SMTP_HOST=smtp-mail.outlook.com
REUT_EMAIL_SMTP_PORT=587
REUT_EMAIL_SMTP_ENCRYPTION=tls
REUT_EMAIL_SMTP_USERNAME=your-email@outlook.com
REUT_EMAIL_SMTP_PASSWORD=your-password
```

#### SendGrid

```env
REUT_EMAIL_SMTP_HOST=smtp.sendgrid.net
REUT_EMAIL_SMTP_PORT=587
REUT_EMAIL_SMTP_ENCRYPTION=tls
REUT_EMAIL_SMTP_USERNAME=apikey
REUT_EMAIL_SMTP_PASSWORD=your-sendgrid-api-key
```

#### Mailgun

```env
REUT_EMAIL_SMTP_HOST=smtp.mailgun.org
REUT_EMAIL_SMTP_PORT=587
REUT_EMAIL_SMTP_ENCRYPTION=tls
REUT_EMAIL_SMTP_USERNAME=your-mailgun-username
REUT_EMAIL_SMTP_PASSWORD=your-mailgun-password
```

### Troubleshooting

#### Email Not Sending

1. **Check Configuration:** Verify all SMTP settings in `.env`
2. **Check Status:** Use `GET /email/status` to verify configuration
3. **Check Logs:** Enable debug mode in development:
   ```php
   // In EmailService.php, set SMTPDebug to 2
   $this->mailer->SMTPDebug = 2;
   ```
4. **Verify Credentials:** Ensure SMTP username and password are correct
5. **Check Firewall:** Ensure port 587 or 465 is not blocked

#### Authentication Errors

- For Gmail, use an App Password instead of your regular password
- Ensure `REUT_EMAIL_SMTP_USERNAME` and `REUT_EMAIL_SMTP_PASSWORD` are set correctly
- Check that SMTP authentication is enabled on your email provider

#### Connection Timeout

- Verify SMTP host and port are correct
- Check if your server allows outbound connections on the SMTP port
- Try different encryption methods (tls vs ssl)
- Some providers require specific ports (587 for TLS, 465 for SSL)

### License

MIT License

### Support

- **GitHub Issues:** https://github.com/m4rcTr3y/Reut-Email/issues
- **Email:** marctrevis61@proton.me

---

## Creating Your Own Plugin

REUT's plugin system makes it easy to extend the framework with custom functionality. Here's how to create your own plugin.

### Plugin Structure

A REUT plugin should follow this structure:

```
your-plugin/
├── composer.json
├── README.md
└── src/
    ├── YourPluginRouter.php
    ├── YourPluginService.php
    └── Config/
        └── YourPluginConfig.php
```

### Step 1: Create Composer Package

Create a `composer.json` file:

```json
{
    "name": "reut/your-plugin",
    "description": "Your plugin description",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your@email.com"
        }
    ],
    "require": {
        "php": ">=7.4",
        "reut/core": "^1.1"
    },
    "autoload": {
        "psr-4": {
            "Reut\\YourPlugin\\": "src/"
        }
    },
    "keywords": ["reut", "framework", "plugin"]
}
```

### Step 2: Create Router

Create a router class that extends `Reut\Auth\NoAuth`:

```php
<?php

declare(strict_types=1);

namespace Reut\YourPlugin;

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\NoAuth;
use Reut\Router\ReuteRoute;

class YourPluginRouter extends NoAuth
{
    protected array $config;
    protected array $pluginConfig;

    public function __construct(App $app, array $config, array $pluginConfig)
    {
        $this->config = $config;
        $this->pluginConfig = $pluginConfig;
        parent::__construct($app);
    }

    protected function genRoutes(): void
    {
        $routes = ReuteRoute::use($this->app);
        
        $routes->group('/your-plugin', 'Your Plugin', function ($group) {
            $group->get('/status', function (Request $request, Response $response) {
                $response->getBody()->write(json_encode([
                    'status' => 'ok',
                    'plugin' => 'your-plugin'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }, 'Get plugin status');
        });
    }
}
```

### Step 3: Create Configuration Class

Create a configuration class:

```php
<?php

declare(strict_types=1);

namespace Reut\YourPlugin\Config;

class YourPluginConfig
{
    public static function load(): array
    {
        return [
            'enabled' => (strtolower($_ENV['REUT_YOUR_PLUGIN_ENABLED'] ?? 'false')) === 'true',
            'setting1' => $_ENV['REUT_YOUR_PLUGIN_SETTING1'] ?? 'default',
            'setting2' => $_ENV['REUT_YOUR_PLUGIN_SETTING2'] ?? 'default',
        ];
    }

    public static function isEnabled(array $config): bool
    {
        return $config['enabled'] ?? false;
    }

    public static function validate(array $config): array
    {
        $errors = [];
        
        if (empty($config['setting1'])) {
            $errors[] = 'setting1 is required';
        }
        
        return $errors;
    }
}
```

### Step 4: Integration

Users integrate your plugin by adding it to `routers/routes.php`:

```php
<?php

use Reut\YourPlugin\YourPluginRouter;
use Reut\YourPlugin\Config\YourPluginConfig;
use Slim\App;

return function (App $app, array $config): void {
    // ... existing routes ...

    // Register your plugin routes
    if ((strtolower($_ENV['REUT_YOUR_PLUGIN_ENABLED'] ?? 'false')) === 'true') {
        $pluginConfig = YourPluginConfig::load();
        if (YourPluginConfig::isEnabled($pluginConfig)) {
            new YourPluginRouter($app, $config, $pluginConfig);
        }
    }
};
```

### Step 5: Documentation

Create comprehensive documentation:

1. **Installation instructions**
2. **Configuration options**
3. **API endpoints** (if applicable)
4. **Usage examples**
5. **Troubleshooting guide**

### Best Practices

1. **Naming Convention:** Use `reut/` prefix for package name
2. **Namespace:** Use `Reut\YourPlugin\` namespace
3. **Configuration:** Use environment variables with `REUT_YOUR_PLUGIN_` prefix
4. **Error Handling:** Provide clear error messages
5. **Testing:** Write tests for your plugin
6. **Documentation:** Keep documentation up to date

### Publishing Your Plugin

1. **Version Control:** Use Git for version control
2. **Packagist:** Submit to [Packagist](https://packagist.org)
3. **Documentation:** Create a README.md with installation and usage instructions
4. **License:** Include a LICENSE file (MIT recommended)

### Example Plugin

Check out the [Email Plugin](https://github.com/m4rcTr3y/Reut-Email) as a reference implementation.

---

## Plugin Submission

Have you created a REUT plugin? We'd love to feature it!

1. **Submit to Packagist:** Make your plugin available via Composer
2. **Open an Issue:** Submit a request to add your plugin to the official list
3. **Documentation:** Ensure your plugin has clear documentation

---

*Last updated: 2024*

