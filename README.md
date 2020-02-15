Send Me SFMOMA
==============

"Send Me SFMOMA" is open source software from the [San Francisco Museum of Modern Art](https://www.sfmoma.org/) that replies to text messages by sending art.  For example, if someone were to text "send me the ocean", the software might respond by sending Pirkle Jones' [Breaking Wave, Golden Gate](https://www.sfmoma.org/artwork/70.40.1/).  You can use this software set up a similar service for your museum or art collection.

See SFMOMA's [introductory post](https://www.sfmoma.org/read/send-me-sfmoma/) (2017) and [follow-up post](https://www.sfmoma.org/read/all-good-things-must-come-to-an-end-the-sunset-of-send-me-sfmoma/) (2020) for more about this project.

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