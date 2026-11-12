<?php
require_once __DIR__ . '/vendor/autoload.php';

use DirectoryTree\ImapEngine\Mailbox;

// Time limit: 120 seconds.
set_time_limit(120);

function storeMail($user) {
    $message = '';
    $mailBox = getMailBox();
    if (!$mailBox) {
        $message = t('mailbox_open_failed');
        return $message;
    }

    $mailArray = getAttachedMail($mailBox, $user);
    $mailBox->disconnect();
    if (count($mailArray) == 0) {
        return '';
    }

    $message = mailRegister($mailArray, $user);

    return $message;
}

function getMailBox() {
    $mailBox = new Mailbox([
        'host' => _MAIL_SERVER,
        'port' => _MAIL_PORT,
        'encryption' => _MAIL_ENCRYPTION, // '' (plain), 'ssl', 'tls' or 'starttls'
        'username' => _MAIL_USER,
        'password' => _MAIL_PASS,
    ]);

    // Open the mailbox with the configured account.
    try {
        $mailBox->connect();
    } catch (Throwable $e) {
        return false;
    }
    return $mailBox;
}

function getAttachedMail($mailBox, $user) {
    $inbox = $mailBox->inbox();
    $messages = $inbox->messages()->withHeaders()->withBody()->get();

    $mailArray = array();
    foreach ($messages as $msg) {
        // Only handle mail whose subject is the user id and that has attachments.
        if ($msg->subject() !== $user || !$msg->hasAttachments()) {
            continue;
        }

        $mail = array();
        $mail['date'] = $msg->date()?->toRfc2822String();
        $mail['from'] = $msg->from()?->email();

        $imgArray = array();
        foreach ($msg->attachments() as $attachment) {
            if (!preg_match('#^image/(jpeg|bmp|png|gif)$#i', $attachment->contentType(), $match)) {
                // Skip anything that is not JPEG / BMP / PNG / GIF.
                continue;
            }
            // Name the image from the current time.
            $mic_time = microtime();
            $fileName = substr($mic_time, 11) . substr($mic_time, 2, 6);
            $fileName .= '.' . strtolower($match[1]);

            $img = array('name' => $fileName, 'body' => $attachment->contents()); // decoded image bytes
            $imgArray[] = $img;
        }
        $mail['img'] = $imgArray;
        $mailArray[] = $mail;
        // Flag the message for deletion.
        $msg->markDeleted();
    }
    // Expunge the flagged messages.
    $inbox->expunge();

    return $mailArray;
}

/*
 * Save the images attached to mail addressed to $user.
 */
function mailRegister($mailArray, $user)
{
    $errorMessage = '';

    foreach ($mailArray as $mail) {
        foreach ($mail['img'] as $img) {
            $error = '';
            if (!$fp = fopen(_IMAGE_DIR . $img['name'], 'w')) {
                $error = t('file_open_failed', $img['name']);
            }
            if ($error == '') {
                if (!fwrite($fp, $img['body'])) {
                    $error = t('file_write_failed', $img['name']);
                }
                fclose($fp);
            }
            if ($error == '' && !insertDB($img['name'], $user)) {
                $error = t('db_insert_failed');
            }
            if ($error) {
                $errorMessage .= $error . '<br>';
            }
        }
    }

    return $errorMessage;
}

/*
 * Insert a row for the file named $fileName owned by $user.
 */
function insertDB($fileName, $user)
{
    $dao = new ImagesDAO();

    // Run the query.
    if (!$dao->insert($user, $fileName)) {
        return false;
    }
    return true;
}
