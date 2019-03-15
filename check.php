<?php

// Try to connect to the server every request.
// If the server is down, send a notification, update config to indicate when the message was sent.
// If the server is up, do nothing.
// If the server was down and the server is now up, send another notification.

function _isJSON($string) {
  json_decode($string);
  return (json_last_error() == JSON_ERROR_NONE);
}

// ============================== //
// Vars
// ============================== //

define('JSON_HTTP_URL', 'https://linode.ghazlawl.com/projects/jazzybot/config.json?' . rand(1111, 9999));
define('JSON_ABSOLUTE_FILEPATH', '/var/www/html/linode.ghazlawl.com/public_html/projects/jazzybot/config.json');

// ============================== //
// Load Config
// ============================== //

// Load the JSON file.
$json = file_get_contents(JSON_HTTP_URL);

if (!_isJSON($json)) {
  echo 'Error parsing JSON!';
  exit;
}

// Decode the JSON.
$json = json_decode($json, TRUE);

// Get the servers.
$servers = $json['servers'];

// ============================== //
// Functions
// ============================== //

/**
 * Sends a Discord notification.
 *
 * @param $message
 * @param $webhook
 * @param $avatar
 *
 * @author Jimmy K. <jimmy@ghazlawl.com>
 * @since 0.1
 */
function _sendDiscordNotification($message, $webhook, $avatar) {
  // Set the Discord webhook.
  $url = 'https://discordapp.com/api/webhooks/' . $webhook;

  // Build the list of fields.
  $fields = [
    'content' => urlencode($message),
    // 'avatar_url' => $avatar . '?rand=' . rand(1111, 9999),
  ];

  // Hold the fields string.
  $fields_string = '';

  // URL-ify the data for POST.
  foreach ($fields as $key => $value) {
    $fields_string .= $key . '=' . $value . '&';
  }

  // Remove the trailing ampersand.
  rtrim($fields_string, '&');

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, count($fields));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
  $result = curl_exec($ch);
  curl_close($ch);

  var_dump($result);
}

function _processOfflineResponse($server, $index) {
  global $json;

  echo "Processing offline response.\n";

  // Check if the offline response has already been sent.
  if ($json['servers'][$index]['message_sent'] != "1") {
    // Set the message.
    if (!empty($server['role'])) {
      $message = "<{$server['role']}> **{$server['name']}** has gone offline.";
    } else {
      $message = "**{$server['name']}** has gone offline.";
    }

    // Send the response.
    _sendDiscordNotification($message, $server['webhook'], $server['avatar']);
    echo "Message sent to Discord.\n";

    // Update the flag.
    $json['servers'][$index]['message_sent'] = "1";
  }
}

function _processOnlineResponse($server, $index) {
  global $json;

  echo "Processing online response.\n";

  // Check if the offline response has already been sent.
  if ($json['servers'][$index]['message_sent'] == "1") {
    // Set the message.
    if (!empty($server['role'])) {
      $message = "<{$server['role']}> **{$server['name']}** is back online!";
    } else {
      $message = "**{$server['name']}** is back online!";
    }

    // Send the response.
    _sendDiscordNotification($message, $server['webhook'], $server['avatar']);
    echo "Message sent to Discord.\n";

    // Update the flag.
    $json['servers'][$index]['message_sent'] = "0";
  }
}

// ============================== //
// Test
// ============================== //

if (isset($_GET['test'])) {
  foreach ($servers as $k => $server) {
    if ($k == $_GET['server']) {
      // Set the message.
      if (!empty($server['role'])) {
        $message = "<{$server['role']}> This is a test of the Ghazlawl Server Alert System. Please disregard!";
      } else {
        $message = "This is a test of the Ghazlawl Server Alert System. Please disregard!";
      }

      // Send the notification.
      _sendDiscordNotification($message, $server['webhook'], $server['avatar']);
    }
  }

  echo "Test message sent.\n";
  exit;
}

// ============================== //
// Socket Connection
// ============================== //

foreach ($servers as $k => $server) {
  // Attempt to create the socket.
  echo "Creating socket for {$server['address']}:{$server['port']}... ";
  $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

  if ($socket === FALSE) {
    echo "ERROR!\n";
  } else {
    echo "OK.\n";
  }

  // Attempt to connect to the socket.
  socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, [
    'sec' => 5,
    'usec' => 5000,
  ]);
  
  $result = @socket_connect($socket, $server['address'], $server['port']);

  if ($result === FALSE) {
    echo "Unable to connect!\n";

    _processOfflineResponse($server, $k);
  } else {
    _processOnlineResponse($server, $k);
  }

  // sleep(2);
}

// ============================== //
// Save Config
// ============================== //

$json = json_encode($json);

if (_isJSON($json)) {
  file_put_contents(JSON_ABSOLUTE_FILEPATH, $json);
} else {
  echo 'Error parsing final JSON!';
  exit;
}
