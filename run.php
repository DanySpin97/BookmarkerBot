<?php

// Include the framework
require './vendor/autoload.php';
require './src/bookmarkerbot.php';
require './src/message.php';
require './src/callback_query.php';
require './src/inline_query.php';
require './src/message_commands.php';
require './src/callback_commands.php';
require './data.php';

// Create the bot
$bot = new BookmarkerBot($token);

// Create redis object
$bot->redis = new Redis();

// Connect to redis database
$bot->redis->connect('127.0.0.1', $redis_port);

$bot->database->connect(
    [
        'adapter' => 'pgsql',
        'username' => $username,
        'password' => $password,
        'dbname' => $database_name
    ]
);

// Load localization from directory
$bot->local->loadAllLanguages();

$bot->answerUpdate["message"] = $message;
$bot->answerUpdate["callback_query"] = $callback_query;
$bot->answerUpdate["inline_query"] = $inline_query;

// Add the answers for commands
$bot->addMessageCommand("start", $start_closure);
$bot->addMessageCommand("about", $about_msg_closure);
$bot->addMessageCommand("help", $help_msg_closure);
$bot->addMessageCommand("delete_bookmarks", $delete_bookmarks_warning_closure);
$bot->addCallbackCommand("menu", $menu_closure);
$bot->addCallbackCommand("browse", $browse_closure);
$bot->addCallbackCommand("channel", $channel_closure);
$bot->addCallbackCommand("help", $help_cbq_closure);
$bot->addCallbackCommand("about", $about_cbq_closure);
$bot->addCallbackCommand("language", $language_closure);
$bot->addCallbackCommand("skip", $skip_closure);
$bot->addCallbackCommand("back", $back_closure);
$bot->addCallbackCommand("deletechannel", $delete_channel_closure);
$bot->addCallbackCommand("changechannel", $change_channel_closure);
$bot->addCallbackCommand("deletebookmark", $delete_bookmarks_closure);
$bot->addCallbackCommand("same/language", $same_language_closure);

// Start the bot
$bot->getUpdatesLocal();
