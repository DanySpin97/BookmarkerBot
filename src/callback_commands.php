<?php

// Each time a callback that has 'menu' as data
$menu_closure = function($bot, $callback_query) {

    // Delete redis junk
    $bot->redis->delete($bot->getChatID() . ':index');
    $bot->redis->delete($bot->getChatID() . ':bookmark_id');

    // Get language
    $bot->getLanguageRedis();

    // Get the user to the menu
    $bot->editMessageText($callback_query['message']['message_id'], $bot->menuMessage(), $bot->keyboard->get());

};

$help_cbq_closure = function($bot, $callback_query) {

    $bot->keyboard->addButton($bot->local[$bot->getLanguageRedis()]['Menu_Button'], 'callback_data', 'menu');

    $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['Help_Msg'], $bot->keyboard->get());

};

// Called when user press "About" button in menu
$about_cbq_closure = function($bot, $callback_query) {

    $bot->getLanguageRedis();

    $bot->keyboard->addButton($bot->local[$bot->language]['Contact_Button'], 'url', 't.me/danyspin97');
    $bot->keyboard->addButton($bot->local[$bot->language]['Framework_Button'], 'url', 'github.com/danyspin97/PhpBotFramework');

    $bot->keyboard->changeRow();

    $bot->keyboard->addButton($bot->local[$bot->language]['Menu_Button'], 'callback_data', 'menu');

    $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['About_Msg'], $bot->keyboard->get());

};

// Called when user want to change language in menu
$language_closure = function($bot, $callback_query) {

    $bot->getLanguageRedis();

    $bot->setStatus(LANGUAGE);

    $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['LanguageOption_Msg'], $bot->keyboard->getChooseLanguageKeyboard());

};

// Will be called each time the user press "Browse" in the menu
$browse_closure = function($bot, $callback_query) {

    // Get language
    $bot->getLanguageRedis();

    $chat_id = $bot->getChatID();

    // Get all bookmarks
    $sth = $bot->pdo->prepare('SELECT id, name, description, url FROM Bookmark WHERE user_id = :chat_id');
    $sth->bindParam(':chat_id', $chat_id);

    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    // If there aren't bookmarks to show
    if ($sth->rowCount() == 0) {

        // Notice the user
        $bot->answerCallbackQuery($bot->local[$bot->language]['NoBookmarkToShow_AnswerCallback']);

        return;

    }

    // Paginate the bookmark received
    $message = PhpBotFramework\Utilities\Paginator::paginateItems($sth, 1, $bot->keyboard, [$bot, 'formatItem'], ITEMS_PER_PAGE);

    // Add a button to go to the menu
    $bot->keyboard->addLevelButtons(['text' => $bot->local[$bot->language]['Menu_Button'], 'callback_data' => 'menu']);

    // Send the message to the user
    $bot->editMessageText($callback_query['message']['message_id'], $message, $bot->keyboard->get());

    // Update the index on redis db
    $bot->redis->set($bot->getChatID() . ':index', 1);

};

$channel_closure = function($bot, $callback_query) {

    // Get language
    $bot->getLanguageRedis();

    // Get channel id
    $channel_id = $bot->getChannelID();

    // If it is valid (the user has a channel)
    if ($channel_id != false) {

        // Get channel data and show them to the user
        $bot->editMessageText($callback_query['message']['message_id'], $bot->getChannelData($channel_id), $bot->keyboard->get());

    } else {

        // Say the user to add the channel
        $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['AddChannel_Msg'], $bot->keyboard->getBackButton());

        // Add the channel
        $bot->setStatus(ADD_CHANNEL);

        // Add message id to redis
        $bot->redis->set($bot->getChatID() . ':message_id', $callback_query['message']['message_id']);

    }

};

// Called when user press a "Skip" button (when adding a bookmark)
$skip_closure = function($bot, $callback_query) {

    // Get language
    $bot->getLanguageRedis();

    // Get id of the message which the user pressed on
    $message_id = $callback_query['message']['message_id'];

    // What is the user skipping?
    switch ($bot->getStatus()) {

    case GET_DESC:

        // Set description NULL for this bookmark
        $bot->redis->hSet($bot->getChatID() . ':bookmark', 'description', 'NULL');

        // Say the user to send the hashtags
        $bot->editMessageText($message_id, $bot->local[$bot->getLanguageRedis()]['Description_Msg'] . $bot->local[$bot->language]['Skipped_Msg'] . NEW_LINE . $bot->local[$bot->language]['SendHashtags_Msg'] . $bot->local[$bot->language]['HashtagsExample_Msg'], $bot->keyboard->getBackSkipKeyboard());

        // Change status
        $bot->setStatus(GET_HASHTAGS);

        break;

        // User skipped hashtags inserting, save the bookmark
    case GET_HASHTAGS:

        // Set hashtags
        $bot->hashtags = [];

        // Save it on the db
        $bot->saveBookmark();

        // Update stats
        $bot->setStatus(MENU);

        // Add keyboard to edit the bookmark
        $bot->addEditBookmarkKeyboard();

        // Add a menu button
        $bot->keyboard->addButton($bot->local[$bot->language]['Menu_Button'], 'callback_data', 'menu');

        // Send the bookmark just created
        $bot->editMessageText($message_id, $bot->formatBookmark(), $bot->keyboard->get());

        // Delete junk in redis
        $bot->redis->delete($bot->getChatID() . ':bookmark');
        $bot->redis->delete($bot->getChatID() . ':message_id');
        $bot->redis->delete($bot->getChatID() . ':index');

        break;

    }

};

// Called on "Back" button pressed
$back_closure = function($bot, $callback_query) {

    // Get language
    $bot->getLanguageRedis();

    // Get id the message which the user pressed the button
    $message_id = $callback_query['message']['message_id'];

    switch ($bot->getStatus()) {

    case GET_NAME:

        // Show the menu to the user
        $bot->editMessageText($message_id, $bot->menuMessage(), $bot->keyboard->get());

        // Change to status as the user is in the menu
        $bot->setStatus(MENU);

        // Delete junk in redis
        $bot->redis->delete($bot->getChatID() . ':bookmark');
        $bot->redis->delete($bot->getChatID() . ':message_id');

        break;

    case GET_DESC:

        // Say the user to insert the name
        $bot->editMessageText($message_id, $bot->local[$bot->language]['SendName_Msg'], $bot->keyboard->getBackButton());

        // Change the status as the user is inserting the name
        $bot->setStatus(GET_NAME);

        break;

    case GET_HASHTAGS:

        // Say the user to insert the description
        $bot->editMessageText($message_id, $bot->local[$bot->language]['SendDescription_Msg'], $bot->keyboard->getBackSkipKeyboard());

        // Change the status as the user is inserting the name
        $bot->setStatus(GET_DESC);

        break;

    case CHANGE_CHANNEL:
        // no break
    case ADD_CHANNEL:
        // No break
    case LANGUAGE:

        // Send the user to the menu
        $bot->editMessageText($message_id, $bot->menuMessage(), $bot->keyboard->get());

        // Update status
        $bot->setStatus(MENU);

    default:

        // Paginate data
        break;

    }

};

$change_channel_closure = function($bot, $callback_query) {

    $bot->getLanguageRedis();

    $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['SendNewChannel_Msg'], $bot->keyboard->getBackButton());

    $bot->setStatus(CHANGE_CHANNEL);

};

$delete_channel_closure = function($bot, $callback_query) {

    $bot->getLanguageRedis();

    $bot->deleteAllBookmarkChannel();

    //Delete channel from user
    $sth = $bot->pdo->prepare('UPDATE "User" SET channel_id = 0 WHERE chat_id = :chat_id');

    $chat_id = $bot->getChatID();
    $sth->bindParam(':chat_id', $chat_id);

    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    $sth = null;

    $bot->editMessageText($callback_query['message']['message_id'], $bot->menuMessage(), $bot->keyboard->get());

    $bot->answerCallbackQuery($bot->local[$bot->language]['DeletedChannel_AnswerCallback'], true);

};

// When user click /delete_bookmarks
$delete_bookmarks_warning_closure = function ($bot, $message) {

    $bot->getLanguageRedis();

    // Has this user any bookmark?
    $sth = $bot->pdo->prepare('SELECT COUNT(id) FROM Bookmark WHERE user_id = :chat_id');

    $chat_id = $bot->getChatID();
    $sth->bindParam(':chat_id', $chat_id);

    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    $result = $sth->fetchColumn();

    $sth= null;

    // If he hasn't
    if ($result == false) {

        // Button for going to the menu
        $bot->keyboard->addButton($bot->local[$bot->language]['Menu_Button'], 'callback_data', 'menu');

        // Say him that he has no bookmarks to delete
        $bot->sendMessage($bot->local[$bot->language]['NoBookmarkToDelete_Msg'], $bot->keyboard->get());

        return;

    }

    // Add 2 buttons, one for going to the menu
    $bot->keyboard->addButton($bot->local[$bot->language]['Menu_Button'], 'callback_data', 'menu');

    // And one for deleting all bookmarks
    $bot->keyboard->addButton($bot->local[$bot->language]['DeleteBookmarks_Button'], 'callback_data', 'deletebookmark');

    // Ask the user if he is sure he wanna delete the bookmarks
    $bot->sendMessage($bot->local[$bot->language]['DeleteBookmarksWarning_Msg'], $bot->keyboard->get());

};

$delete_bookmarks_closure = function ($bot, $callback_query) {

    $bot->getLanguageredisAsCache();

    // If the user hasn't pressed the button
    if (!$bot->redis->exists($bot->getChatID() . ':delete_flag')) {

        // Set a flag that will be alive for the next minute
        $bot->redis->setEx($bot->getChatID() . ':delete_flag', 120, 1);

        $bot->answerCallbackQuery($bot->local[$bot->language]['DeleteBookmarksWarning_AnswerCallback'], true);

        return;

    }

    // Delete all bookmarks from channel
    $bot->deleteAllBookmarkChannel();

    $chat_id = $bot->getChatID();

    // Get id of each bookmark
    $sth = $bot->pdo->prepare('SELECT id FROM Bookmark WHERE user_id = :chat_id');
    $sth->bindParam(':chat_id', $chat_id);

    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    // Delete all bookmark hashtags
    $sth2 = $bot->pdo->prepare('DELETE FROM Bookmark_tag WHERE bookmark_id = :bookmark_id');

    while ($row = $sth->fetch()) {

        $sth2->bindParam(':bookmark_id', $row['id']);

        try {

            $sth2->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

    }

    $sth = null;
    $sth2 = null;

    // Delete all bookmarks
    $sth = $bot->pdo->prepare('DELETE FROM Bookmark WHERE user_id = :chat_id');
    $sth->bindParam(':chat_id', $chat_id);

    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    $sth = null;

    // Notice the user that the bookmarks has been deleted
    $bot->answerCallbackQuery($bot->local[$bot->language]['BookmarksDeleted_AnswerCallback']);

    // Send the menu to the user
    $bot->editMessageText($callback_query['message']['message_id'], $bot->menuMessage(), $bot->keyboard->get());

    // Delete the flag on redis
    $bot->redis->delete($bot->getChatID() . ':delete_flag');

};

$same_language_closure = function($bot, $callback_query) {

    // Say the user he choosed the same language the bot is set
    $bot->answerCallbackQuery($bot->local[$bot->getLanguageRedis()]['SameLanguage_AnswerCallback']);

};