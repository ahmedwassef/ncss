# WordPress Migrate Module

A comprehensive Drupal 11 module for migrating content from WordPress to Drupal.

## Features

- **Complete Content Migration**: Migrate posts, pages, users, media files, categories, and tags
- **Database Connection**: Secure connection to WordPress MySQL database
- **Media Processing**: Automatic download and processing of WordPress media files
- **Taxonomy Migration**: Convert WordPress categories and tags to Drupal taxonomies
- **User Migration**: Migrate WordPress users with metadata
- **Batch Processing**: Process large amounts of content in configurable batches
- **Migration Logging**: Track migration progress and handle errors
- **Preview Mode**: Preview WordPress data before migration
- **Skip Existing**: Option to skip already migrated content

## Requirements

- Drupal 11
- PHP 8.1+
- MySQL/MariaDB access to WordPress database
- Network access to WordPress site (for media files)

## Installation

1. Place the module in `web/modules/custom/wordpress_migrate/`
2. Enable the module: `drush en wordpress_migrate`
3. Configure the module at `/admin/config/development/wordpress-migrate`

## Configuration

### Database Settings

Configure your WordPress database connection:

- **Database Host**: WordPress database server hostname
- **Database Port**: Database port (usually 3306)
- **Database Name**: WordPress database name
- **Database Username**: Database username
- **Database Password**: Database password
- **Table Prefix**: WordPress table prefix (usually `wp_`)

### WordPress Site Settings

- **WordPress Base URL**: Full URL of your WordPress site (e.g., `https://example.com`)

### Migration Settings

- **Batch Size**: Number of items to process per batch (default: 50)
- **Skip Existing Content**: Skip content that has already been migrated
- **Create Content Types**: Automatically create content types for WordPress content

## Usage

### 1. Test Connection

Before running the migration, test your database connection:

1. Go to `/admin/config/development/wordpress-migrate`
2. Fill in your WordPress database settings
3. Click "Test Connection"
4. Verify the connection is successful

### 2. Preview Data

Preview the WordPress data that will be migrated:

1. Click "Preview Data" on the settings page
2. Review the content types and counts
3. Verify the data looks correct

### 3. Run Migration

Start the migration process:

1. Go to `/admin/config/development/wordpress-migrate/run`
2. Select the content types you want to migrate
3. Click "Start Migration"
4. Monitor the progress in the migration logs

## Content Types Created

The module automatically creates the following content types:

- **WordPress Post**: For migrated WordPress posts
- **WordPress Page**: For migrated WordPress pages

## Taxonomies Created

The module creates the following taxonomies:

- **WordPress Categories**: For WordPress categories
- **WordPress Tags**: For WordPress tags

## Migration Process

### Users
- Migrates WordPress users to Drupal users
- Preserves user metadata (display name, first name, last name)
- Maintains user registration dates

### Posts and Pages
- Converts WordPress posts to Drupal nodes
- Preserves content, titles, and publication dates
- Maintains author relationships
- Associates categories and tags

### Media Files
- Downloads media files from WordPress
- Creates Drupal media entities
- Preserves alt text and descriptions
- Organizes files in `public://wordpress-migrate/`

### Categories and Tags
- Creates Drupal taxonomies
- Preserves hierarchy and descriptions
- Maintains post counts

## Migration Logging

The module logs all migration activities in the `wordpress_migrate_log` table:

- Migration type (users, posts, media, etc.)
- WordPress ID and corresponding Drupal ID
- Migration status (success, failed, skipped)
- Error messages and timestamps

## Troubleshooting

### Database Connection Issues

- Verify database credentials
- Check network connectivity
- Ensure WordPress database is accessible
- Verify table prefix is correct

### Media Download Issues

- Check WordPress base URL is correct
- Verify network access to WordPress site
- Check file permissions in Drupal
- Review media file URLs in WordPress

### Memory Issues

- Reduce batch size in migration settings
- Increase PHP memory limit
- Process content types separately

## Security Considerations

- Store database credentials securely
- Use read-only database user if possible
- Validate WordPress base URL
- Review migrated content for security issues

## Performance Tips

- Run migration during low-traffic periods
- Increase PHP memory limit for large sites
- Use appropriate batch sizes
- Monitor server resources during migration

## Support

For issues and questions:

1. Check the migration logs at `/admin/reports/dblog`
2. Review the module's error messages
3. Test database connection separately
4. Verify WordPress site accessibility

## License

This module is provided as-is for educational and development purposes.
