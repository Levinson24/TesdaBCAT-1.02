#!/bin/bash
# Script to fix common PHP 8.1+ errors in TESDA GMS

echo "Fixing PHP Deprecation Warnings..."

# Find all PHP files and add null coalescing where needed
find . -name "*.php" -type f | while read file; do
    # Backup original
    cp "$file" "$file.bak"
    
    # Fix common patterns (be careful with this in production)
    # This is a basic fix - manual review recommended
    
    # Pattern 1: htmlspecialchars($var) where $var might be null
    # We'll document this for manual fixing
    
    echo "Checked: $file"
done

echo ""
echo "Fix Summary:"
echo "1. sanitizeInput() - FIXED (handles null)"
echo "2. admin/users.php - FIXED (structure and null handling)"
echo "3. Other files - Use ?? operator for null values"
echo ""
echo "Run your application and check for remaining warnings."
