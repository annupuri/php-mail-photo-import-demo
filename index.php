<?php require 'i18n.php'; ?>
<html>
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <title><?= APP_TITLE ?></title>
  </head>
  <body>
    <div><?= lang_switcher() ?></div>
    <form action="./main.php" method="get">
      <table>
        <tr>
          <td><?= t('user_id') ?></td>
          <td>
            <input type="text" size="20" name="user">
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <input type="submit" value="<?= t('log_in') ?>">
          </td>
        </tr>
      </table>
    </form>
  </body>
</html>
