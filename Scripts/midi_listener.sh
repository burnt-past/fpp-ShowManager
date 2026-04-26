#!/bin/bash

# Monitor MIDI input and process volume changes
amidi -p hw:2,0,0 -d | while read -r line; do
    # Extract the MIDI volume value from the input (adjust parsing as needed)
    midi_volume=$(echo $line | awk '{print $4}')

    # Call the PHP script to update FPP volume
    php /home/fpp/media/plugins/ShowManager/output.php "$midi_volume"
done
