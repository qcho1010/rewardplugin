#!/bin/bash

# Check if PHPUnit is installed
if ! command -v phpunit &> /dev/null; then
    echo "PHPUnit is not installed. Please install it first."
    exit 1
fi

# Get directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$(dirname "$DIR")"

# Run the tests
cd "$PLUGIN_DIR"
phpunit 