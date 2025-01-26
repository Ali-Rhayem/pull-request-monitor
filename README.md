# GitHub PR Monitor

A Laravel application that automatically monitors GitHub pull requests and exports the data to both local files and Google Sheets.

## Features

- Fetches pull request information from configured GitHub repositories
- Monitors different PR categories:
  - Old pull requests (>14 days)
  - Pull requests requiring review
  - Pull requests with successful status checks
  - Pull requests with no review requests
- Exports data to:
  - Local text files
  - Google Sheets (automated spreadsheet creation and updates)
- Scheduled automatic updates every 15 minutes
- Queue-based processing for better performance

## Requirements

- PHP 8.2+
- Laravel 11.x
- Google Sheets API credentials
- GitHub API access
- Database (for queue processing)

## Installation

1. Clone the repository
2. Install dependencies: