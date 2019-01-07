Send Me SFMOMA
==============

Setup
-----

1. Clone this repo to a local directory.
2. Obtain needed info for config file (see config_sample.php).
3. `composer install` for directory root to download the Twilio and Mixpanel libraries.

Testing
-------

Once your local environment is set up, you can trigger responses to your phone via the browser: `http://sendme.local/?Body=send%20me%20cactus`

When local environment is detected the application will use the to/from phone numbers set in the config file.

Other Info
----------

You can find the list that maps emoji to words here: https://gist.github.com/jaymollica/cf179e0facabbb4ec8cf9e69804b7cb1