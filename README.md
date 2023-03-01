# APSystems-MQTT
This is yet another simple script from me, based on the same code-base as most of my MQTT home automation scripts.

So far, the script has been tested for about five minutes, but based on the information gathered bu others, it should be safe to run. It only runs two standard queries to the ECU every 60 seconds.

The script is highly based on the work of others, and most of the information found to create this came from npeter's great README here: https://github.com/npeter/ioBroker.apsystems-ecu

In order to run this script, you (obviously) need PHP installed. This script utilizes Bluerhinos' project phpMQTT, which can be found here: https://github.com/bluerhinos/phpMQTT 

In order to run the script, you need to install the scriptfile from Bluerhinos, called phpMQTT.php in the same directory as APSystems-MQTT.php in order for the solution to work. You also need write permissions in the folder where this is run, in order to save the configuration file.

Upon first run, you will be asked to provide information on the IP address of your APSystems ECU, and the port. For now, we know the port is always 8899, so that is the default, so you should never need to change this. You also have to provide the IP of your MQTT broker, and logon information for it.

If you ever need to recreate the config file, you can either delete the one created, or run the script with the switch "-c".

The script has been written to run as a daemon process, so it will stay running "forever", and will query the IP address every 60 seconds. If you want to run it as a single instance, e.g. from cron, or just to test, you can pass the switch "-s", and it will just do a single pass, and exit.

Finally, if something doesn't seem right, there is a debug option "-d", which will provide information on what the script attempts to do.

