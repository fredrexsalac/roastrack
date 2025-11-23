<?php
// Copy this file to google_oauth.php and fill in your credentials
// Google Cloud Console -> Credentials -> Create OAuth 2.0 Client IDs (Web application)
// Authorized redirect URI should be: http://localhost/google_callback.php (adjust if hosted elsewhere)
return [
  'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
  'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
  'redirect_uri' => 'http://localhost/google_callback.php',
  'scopes' => [
    'openid',
    'email',
    'profile'
  ],
];
