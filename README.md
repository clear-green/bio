# bio
Block.io php handler for Slack

This is a bot for Slack, enabling you to operate your Block.io account.

Requirements:

This bot uses block_io-php library. Requirements for this library are at https://github.com/BlockIo/block_io-php

The Linux server, where you would like to host it must be installed with all the php extensions that block_io library requires and also support a secure connection.

Installation

Put the file into a web-accessible directory and make sure the file is able to create files in this directory. The handler must create a file with your user id as a filename where it stores the encrypted credentials to your Block.io account.

Note the url of the file. eg. https://besthost.com/test_bio_04.php

Create an empty file called virginity in the same directory. The handler deletes this file when registering the owner.

Create a slash command in Integrations under your Slack account. eg. /bio

Enter the url of your handler when registering the slash command.

Usage

/bio 

will generate an empty reply

/bio hi

the first time will generate an offer to register

/bio reg [your Block.io API key] [your Block.io pin]

will make you an owner of the bot or will enable you to change the credentials

/bio balance

will give you the available balance on your Block.io account

/bio newaddress [amount] [label]

will give you a new Bitcoin address with an Bitcoin url shortened at leg.gy url shortener to make it a http link (Slack parser does not support bitcoin: links) and a QR cde picture for the url

/bio newaddress [amount]

is the same as above, but gives you a label, created by Block.io

/bio newaddress

is the same as above, but with the expected amount at 0

/bio transfer [amount] [label from] [label to]

will transfer the amount specified from the Bitcoin label specified to another specified label


