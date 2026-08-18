<?php
// Database config — change according to your MySQL setup
return [
    'db_host'   => 'localhost',
    'db_user'   => 'root',
    'db_pass'   => '',
    'db_name'   => 'weighbridge_bltransport_db',

    // Company info — change for each installation / client
    'company'   => [
        'name'    => 'Your Company Name',
        'address' => "Address line 1\nAddress line 2",
        'phone'   => '',
        'gst'     => '',
    ],

    'slip_prefix' => 'WB-',
    'date_format' => 'd/m/Y',
    'time_format' => 'H:i',
	
	    'apacs' => [
        'login_id' => '41263253',
        'password' => 'T8&zR5^hW1*Jm9!C',
        'login_url' => 'https://apacs.chennaiport.gov.in/api/operator/login',
        'post_url' => 'https://apacs.chennaiport.gov.in/api/cargo/weighbridge'
    ]
];
?>

