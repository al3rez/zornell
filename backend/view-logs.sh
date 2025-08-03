#!/bin/bash

echo "=== Zornell Error Logs ==="
echo ""

# Check for API errors log
if [ -f "data/api-errors.log" ]; then
    echo "📋 API Errors (last 20 lines):"
    echo "--------------------------------"
    tail -n 20 data/api-errors.log
    echo ""
fi

# Check for general PHP errors
if [ -f "data/error.log" ]; then
    echo "📋 PHP Errors (last 20 lines):"
    echo "--------------------------------"
    tail -n 20 data/error.log
    echo ""
fi

# Check database file permissions
echo "📁 Database Permissions:"
echo "--------------------------------"
ls -la data/zornell.db 2>/dev/null || echo "Database file not found!"
echo ""

# Check directory permissions
echo "📂 Directory Permissions:"
echo "--------------------------------"
ls -la data/
echo ""

# Live monitoring option
echo "💡 To monitor logs in real-time, run:"
echo "   tail -f data/api-errors.log data/error.log"