<?php

return [
    // Let customers take a queue number straight from the outlet page (party size form),
    // without the WhatsApp chatbot. Useful for demos and while WhatsApp is not set up.
    'web_signup' => (bool) env('SATSET_WEB_SIGNUP', false),
];
