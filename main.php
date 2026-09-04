<?php
require 'i18n.php';
require 'dao.php';
require 'mail.php';

$user = $_GET['user'] ?? $_POST['user'] ?? '';

if (!$user) {
    // Not logged in: go back to the login page.
    header('Location: ./index.php');
    exit;
}

$dao = new ImagesDAO();
$error = '';

if (isset($_POST['store'])) {
    $error = storeMail($user);
} else if (isset($_POST['delete'])) {
    $id = $_POST['id'] ?? '';
    // Look up the photo's file name.
    $result = $dao->selectByIdAndUser($id, $user);
    if ($image = $result->fetch_array(MYSQLI_ASSOC)) {
        $fileName = $image['file_name'];
        if ($dao->delete($id, $user)) {
            unlink(_IMAGE_DIR . $fileName);
        }
    }
}

// Run the query.
$result = $dao->selectByUser($user);

$images = array();

// Fetch each row as an associative array (MYSQLI_ASSOC).
while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
    $images[] = $row;
}
?>

<html>
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <title><?= APP_TITLE ?></title>
  </head>
  <body>
    <div><?= lang_switcher() ?></div>
    <?= t('user_id') ?>:<?= h($user) ?><br>
    <a href="./index.php"><?= t('back_to_login') ?></a>
    <form action="./main.php" method="post">
      <input type="hidden" name="user" value="<?= h($user) ?>">
      <input type="submit" name="store" value="<?= t('fetch_mail') ?>">
    </form>
    <?php if ($error) { echo "<span style='color: red;'>$error</span>"; } ?>
    <table border="1">
  <?php foreach ($images as $image) { // One block per photo. ?>
      <tr>
        <td>
          <table>
            <tr>
              <td colspan="2"><b><?= h($image['title']) ?></b>
                <a href="./edit.php?id=<?= h($image['id']) ?>&user=<?= h($image['user']) ?>"><?= t('edit') ?></a>
              </td>
            </tr>
            <tr valign="top">
              <td>
                <img src="./images/<?= h($image['file_name']) ?>">
              </td>
              <td>
                <p>
                <u><?= t('place') ?>:</u><br>
                &nbsp;&nbsp;<a href="<?= h($image['place']) ?>" target="_blank"><?= h($image['place']) ?></a>
                </p>
                <p>
                <u><?= t('comment') ?>:</u><br>
                &nbsp;&nbsp;<?= h($image['comment']) ?>
                </p>
              </td>
            </tr>
            <tr>
              <td>
                <form action="./main.php" method="post">
                  <input type="hidden" name="id" value="<?= h($image['id']) ?>">
                  <input type="hidden" name="user" value="<?= h($user) ?>">
                  <input type="submit" name="delete" value="<?= t('delete') ?>">
                </form>
              </td>
            </tr>
          </table>
        </td>
      </tr>
  <?php } ?>
    </table>
  </body>
</html>
