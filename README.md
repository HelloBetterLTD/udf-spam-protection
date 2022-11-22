# User forms spam protection

This module acts as a filter for User Defined Forms. Also works with Userforms element. For websites that has constant spam issues and spammers getting through Recaptcha or any other gateway, this acts as a lower level screening module. 

## Maintainers
nivanka@silverstripers.com

## Installation

Use composer to install on your SilverStripe 4 website.

```
composer require silverstripers/udf-spam-protection dev-master
```

## Requirements

1. Silverstripe 4+
2. Silverstripe Userforms

## Usage 

Once the module is installed it gives you a set of configuration options that can be used to filter / idenfity spam. The options are in the Siteconfig/settings. Lets you set up

1. IP addresses you want to block
2. Keywords and patterns that you want to block 
3. Email addresses that you want to block
4. Set up throttling rates per IP address.

PS: thanks for CraftCMS Free Form plugin for the idea :)
