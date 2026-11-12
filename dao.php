<?php

// Mail server that the photo-bearing messages are fetched from.
define('_MAIL_SERVER', '127.0.0.1');        // IMAP server host name
define('_MAIL_PORT', 3143);                 // IMAP server port
define('_MAIL_ENCRYPTION', '');             // '' (plain), 'ssl', 'tls' or 'starttls'
define('_MAIL_USER', 'demo');               // mailbox account
define('_MAIL_PASS', 'demo');               // mailbox password
define('_IMAGE_DIR', './images/');          // directory the decoded images are written to

// Database connection settings.
define('_DB_HOST', 'localhost');
define('_DB_USER', 'demo');
define('_DB_PASS', 'demo');
define('_DB_NAME', 'uploader');

class ImagesDAO extends mysqli
{
    public function __construct()
    {
        parent::__construct(_DB_HOST, _DB_USER, _DB_PASS, _DB_NAME);
        if ($this->connect_errno) {
            throw new Exception($this->connect_error);
        }
        parent::set_charset('utf8mb4');
    }

    public function __destruct()
    {
        parent::close();
    }

    /**
     * Prepare $sql, bind $params according to $types ('s', 'i', ...) and execute.
     * Returns the executed mysqli_stmt.
     */
    private function run($sql, $types = '', array $params = [])
    {
        $stmt = parent::prepare($sql);
        if ($stmt === false) {
            echo 'ErrorMessage: ' . $this->error . '<br>';
            exit();
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            echo 'ErrorMessage: ' . $stmt->error . '<br>';
            exit();
        }

        return $stmt;
    }

    public function selectByUser($user)
    {
        $stmt = $this->run(
            'SELECT * FROM images WHERE user = ? ORDER BY id DESC',
            's',
            [$user]
        );

        return $stmt->get_result();
    }

    public function selectByIdAndUser($id, $user)
    {
        $stmt = $this->run(
            'SELECT * FROM images WHERE id = ? AND user = ?',
            'is',
            [$id, $user]
        );

        return $stmt->get_result();
    }

    public function insert($user, $fileName)
    {
        $this->run(
            'INSERT INTO images (file_name, user) VALUES (?, ?)',
            'ss',
            [$fileName, $user]
        );

        return true;
    }

    public function update($id, $user, $title, $place, $comment)
    {
        $this->run(
            'UPDATE images SET title = ?, place = ?, `comment` = ? WHERE id = ? AND user = ?',
            'sssis',
            [$title, $place, $comment, $id, $user]
        );

        return true;
    }

    public function delete($id, $user)
    {
        $this->run(
            'DELETE FROM images WHERE id = ? AND user = ?',
            'is',
            [$id, $user]
        );

        return true;
    }
}
