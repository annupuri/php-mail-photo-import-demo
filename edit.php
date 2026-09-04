<?php
require 'i18n.php';
require 'dao.php';

$errorMessage = '';
$submitted = !empty($_POST['update']);

if ($submitted) {
    // The edit form was submitted.
    $id = $_POST['id'] ?? '';            // photo id
    $title = $_POST['title'] ?? '';      // photo title
    $user = $_POST['user'] ?? '';        // user name
    $place = $_POST['place'] ?? '';      // place
    $comment = $_POST['comment'] ?? '';  // comment
} else {
    $id = $_GET['id'] ?? '';       // photo id
    $user = $_GET['user'] ?? '';   // user name
}

if (!$id || !$user) {
    // Nothing to edit: go back to the login page.
    header('Location: ./index.php');
    exit;
}

$dao = new ImagesDAO();

if ($submitted && !$dao->update($id, $user, $title, $place, $comment)) {
    $errorMessage = t('update_failed');
}

// Load the photo being edited.
$result = $dao->selectByIdAndUser($id, $user);
// fetch as an associative array (MYSQLI_ASSOC)
$image = $result->fetch_array(MYSQLI_ASSOC);

if (!$image) {
    $errorMessage = t('no_such_photo');
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
    <a href="./index.php"><?= t('back_to_login') ?></a><br>
    <a href="./main.php?user=<?= h($user) ?>"><?= t('photos') ?></a><br>

    <?php
    if ($errorMessage) {
        // Show the error, if any.
        echo "<span style=\"color: red;\">{$errorMessage}</span>";
    }

    if ($image) {
        // Show the form when the photo exists.
    ?>

    <form action="./edit.php" method="post">
      <table>
        <tr>
          <td><?= t('title') ?></td>
          <td>
            <input type="text" size="30" name="title" value="<?= h($image['title']) ?>">
          </td>
        </tr>
        <tr>
          <td><?= t('place') ?></td>
          <td>
            <input type="text" size="64" name="place" value="<?= h($image['place']) ?>">
          </td>
        </tr>
        <tr>
          <td><?= t('comment') ?></td>
          <td>
            <input type="text" size="128" name="comment" value="<?= h($image['comment']) ?>">
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <input type="submit" value="<?= t('save') ?>" name="update">
          </td>
        </tr>
      </table>
      <input type="hidden" name="id" value="<?= h($id) ?>">
      <input type="hidden" name="user" value="<?= h($user) ?>">
    </form>
    <img src="./images/<?= h($image['file_name']) ?>">
    <?php
    }
    ?>
  </body>
</html>
