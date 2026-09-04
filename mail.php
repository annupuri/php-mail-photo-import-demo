<?php
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
    if (count($mailArray) == 0) {
        return '';
    }

    $message = mailRegister($mailArray, $user);

    return $message;
}

function getMailBox() {
    // e.g. {mail.example.com:143/imap/norsh/notls}INBOX
    $mailbox = '{' . _MAIL_SERVER . ':' . _MAIL_PORT . _MAIL_FLAGS . '}INBOX';

    // Open the mailbox with the configured account.
    return @imap_open($mailbox, _MAIL_USER, _MAIL_PASS);
}

function getAttachedMail($mailBox, $user) {
    mb_internal_encoding('utf-8');
    $boxes = imap_check($mailBox);

    $mailArray = array();
    for ($i = 1; $i <= $boxes->Nmsgs; $i++) {
        $head = imap_headerinfo($mailBox, $i);
        $structure = imap_fetchstructure($mailBox, $i);
        $subject = mb_decode_mimeheader($head->subject);

        // Only handle mail whose subject is the user id and that has attachments.
        if ($subject == $user && $structure->subtype == 'MIXED') {
            $mail = array();
            $mail['date'] = $head->date;
            $mail['from'] = $head->from[0]->mailbox . '@' . $head->from[0]->host;

            $imgArray = array();
            for ($j = 0; $j < count($structure->parts); $j++) {
                if (!preg_match('/^(JPEG|BMP|PNG|GIF)$/', $structure->parts[$j]->subtype, $match)) {
                    // Skip anything that is not JPEG / BMP / PNG / GIF.
                    continue;
                }
                // Name the image from the current time.
                $mic_time = microtime();
                $fileName = substr($mic_time, 11) . substr($mic_time, 2, 6);
                $fileName .= '.' . strtolower($match[0]);

                $fileBody = imap_fetchbody($mailBox, $i, $j + 1);        // image bytes
                $img = array('name' => $fileName, 'body' => $fileBody);
                $imgArray[] = $img;
            }
            $mail['img'] = $imgArray;
            $mailArray[] = $mail;
            // Flag the message for deletion.
            imap_delete($mailBox, $i);
        }

    }
    // Expunge the flagged messages.
    imap_expunge($mailBox);

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
            // Decode the image.
            $decodedImage = imap_base64($img['body']);
            $error = '';
            if (!$fp = fopen(_IMAGE_DIR . $img['name'], 'w')) {
                $error = t('file_open_failed', $img['name']);
            }
            if ($error == '') {
                if (!fwrite($fp, $decodedImage)) {
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
