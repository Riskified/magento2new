

# [Magento 2 fraud protection extension by Riskified](https://www.riskified.com/magento/)

## Overview

This extension allows for automatic and/or manual submission of purchase orders to Riskified for fraud review and guarantee.

If you don't have an existing account, please start by signing up to Riskified [here](https://www.riskified.com) - it's free and takes just a few minutes.

## Features

* Automatic/manual submission of orders to review.
* Order cancellation also excludes it from review.
* Magento order status reflects Riskified's review decision.
* Includes a **Sandbox Environment** option for testing and integration.

https://www.riskified.com/magento/


## Installation

You may install the extension by cloning the repository, downloading the ZIP file, or by using Composer. 

To use Composer, follow these steps in the command line:
```
1. composer config repositories.riskified-decider git git@github.com:Riskified/magento2new.git
2. composer require Riskified/magento2new dev-master
3. php bin/magento module:enable Riskified_Decider
4. php bin/magento setup:upgrade
```