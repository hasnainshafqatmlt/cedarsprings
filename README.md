# Custom Login Plugin

A WordPress plugin that integrates with the Ultracamp API to provide custom login functionality.

## Features

- Custom login form with Ultracamp API integration
- AJAX-based authentication
- Cookie-based session management
- Admin panel for configuration
- Responsive design
- Shortcode support for easy integration

## Installation

1. Upload the `login` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings' > 'Custom Login' to configure the plugin

## Configuration

### Admin Settings

Navigate to **Settings > Custom Login** to configure:

- **Ultracamp API Key**: The API key for Ultracamp authentication (default: 7EJWNDKAMG496K9Q)
- **Camp ID**: The Ultracamp camp ID (default: 107)
- **Help Text**: Custom text to display above the login form

### Usage

#### Shortcode

Use the shortcode `[custom_login_form]` to display the login form on any page or post.

Example:
```
[custom_login_form]
```

#### PHP Code

You can also display the login form programmatically:

```php
<?php echo do_shortcode('[custom_login_form]'); ?>
```

## How It Works

1. **Frontend**: The login form is displayed using a shortcode
2. **JavaScript**: Handles form submission via AJAX
3. **Backend**: WordPress AJAX handler processes the login request
4. **API Integration**: Uses the Ultracamp API to authenticate users
5. **Session Management**: Stores authentication data in cookies

## File Structure

```
login/
├── login.php              # Main plugin file
├── includes/
│   ├── ultracamp.php      # Base Ultracamp API class
│   └── CartAndUser.php    # Extended class with authentication
├── js/
│   └── login-action.js    # Frontend JavaScript
├── css/
│   └── style.css          # Plugin styles
└── README.md              # This file
```

## API Integration

The plugin integrates with the Ultracamp REST API:

- **Authentication Endpoint**: `/api/camps/107/accounts/{account}/authenticate/credentials`
- **Headers**: Uses `ultracamp-camp-api-key` and `ultracamp-account-credentials`
- **Response**: Returns authentication status and user information

## Security Features

- WordPress nonce verification for AJAX requests
- Input sanitization and validation
- Secure cookie handling
- Error handling and user feedback

## Browser Compatibility

- Modern browsers with JavaScript enabled
- Cookie support required
- Responsive design for mobile devices

## Troubleshooting

### Common Issues

1. **Login not working**: Check API key and camp ID in admin settings
2. **JavaScript errors**: Ensure jQuery is loaded
3. **Styling issues**: Check if CSS is loading properly
4. **Cookie issues**: Ensure cookies are enabled in the browser

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and questions, please refer to the plugin documentation or contact the development team.

## Changelog

### Version 1.0.0
- Initial release
- Basic login functionality
- Admin configuration panel
- Shortcode support
- Ultracamp API integration 