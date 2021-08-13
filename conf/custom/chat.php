<?php

// Escalation configuration
return array(
    'chat' => array(
        'enabled' => true,
        'address' => 'tel:+' //Escalation phone number
    ),
    'triesBeforeEscalation' => 3,
    'negativeRatingsBeforeEscalation' => 0
);
