#!/bin/bash
# Script to start the Gmail worker manually
# Use this after configuring Gmail OAuth in the admin panel

echo "Starting Gmail Worker..."
supervisorctl start gmail-worker

echo "Checking status..."
supervisorctl status gmail-worker

echo ""
echo "To view logs:"
echo "  tail -f /var/www/html/logs/worker.log"
echo ""
echo "To stop worker:"
echo "  supervisorctl stop gmail-worker"
