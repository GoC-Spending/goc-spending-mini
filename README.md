# goc-spending-mini

Work-in-progress code to scrape and then parse contracting data from departments' Proactive Disclosure website.

## Install instructions

1. Clone the repository.
2. In the folder, run composer to install the "Guzzle" dependency with, `composer update`

You're ready to go!

## Scraping departments

The scrapers are located in contracts-scraper.php, which can be run with, `php scrapers/contracts-scraper.php`

By default, it will download 2 quarters and 2 contract files from each department that has a scraper function.

## Parsing departments

Parsing data - to extract data from the HTML files downloaded with the scraper - are located in contracts-parser.php, which can be run with, `php scrapers/contracts-parser.php`
