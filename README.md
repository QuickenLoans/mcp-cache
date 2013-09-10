MCP Cache
=========

Caching standard for mcp services

### Dependencies

* MCP Core
    * Time
* Skeletor (Optional)

### Building

    This library should be built with composer only

### Installing

Install development dependencies

    bin/install

Wipe compiled files:

    bin/clean

### Testing

Run PHP Unit

    vendor/bin/phpunit

TDD:

    bin/test <file>
    bin/test --rapid <file>

Run Code Sniffer (PSR2)

    bin/sniff

Run Mess Detector (Strict)

    bin/md
