<?php
include "include/sessionAdmin.php";
include "include/configure.php";
require_once __DIR__ . '/../php/mail_config.php';
require_once __DIR__ . '/../php/whatsapp_tasks.php';

loadMailEnvironment();
$envPath = dirname(__DIR__) . '/.env';
$mergeTags = whatsgTaskMergeTagNames();
$templates = whatsgTaskTemplates();
$message = '';
$messageType = 'success';

if (empty($_SESSION['whatsAppTemplatesCsrf'])) {
    $_SESSION['whatsAppTemplatesCsrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string)($_POST['csrfToken'] ?? '');
    if (!hash_equals((string)$_SESSION['whatsAppTemplatesCsrf'], $postedToken)) {
        $message = 'The page expired. Reload it and try again.';
        $messageType = 'danger';
    } elseif (!is_file($envPath) || !is_readable($envPath) || !is_writable($envPath)) {
        $message = 'The .env file is not available for editing by the application.';
        $messageType = 'danger';
    } else {
        $operation = (string)($_POST['operation'] ?? 'add');
        $label = trim((string)($_POST['label'] ?? ''));
        if ($operation === 'delete') {
            $label = (string)($_POST['deleteLabel'] ?? '');
            if (!array_key_exists($label, $templates)) {
                $message = 'Select a template to remove.';
                $messageType = 'danger';
            } else {
                unset($templates[$label]);
            }
        } else {
            $templateName = trim((string)($_POST['templateName'] ?? ''));
            $supportsMedia = !empty($_POST['supportsMedia']);
            $variables = isset($_POST['variables']) && is_array($_POST['variables']) ? array_values($_POST['variables']) : array();
            $variables = array_values(array_map('trim', $variables));
            $variables = array_values(array_filter($variables, function ($variable) { return $variable !== ''; }));
            if ($label === '' || $templateName === '') {
                $message = 'Enter a dropdown label and the exact approved template name.';
                $messageType = 'danger';
            } elseif (array_key_exists($label, $templates)) {
                $message = 'That dropdown label already exists. Remove it first if you want to replace it.';
                $messageType = 'danger';
            } elseif (count(array_diff($variables, $mergeTags)) > 0) {
                $message = 'One or more selected variables are not supported.';
                $messageType = 'danger';
            } elseif (preg_match('/[\r\n\x00-\x1F]/', $label . $templateName)) {
                $message = 'Labels and template names cannot contain line breaks or control characters.';
                $messageType = 'danger';
            } else {
                $templates[$label] = array('name' => $templateName, 'variables' => $variables, 'supportsMedia' => $supportsMedia);
            }
        }

        if ($message === '') {
            $json = json_encode($templates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
            if ($json === false) {
                $message = 'The template list could not be encoded as JSON.';
                $messageType = 'danger';
            } else {
                $contents = file_get_contents($envPath);
                if ($contents === false) {
                    $message = 'The .env file could not be read.';
                    $messageType = 'danger';
                } else {
                    $newLine = "WHATSG_TEMPLATES_JSON='" . $json . "'";
                    $file = fopen($envPath, 'c+');
                    if (!$file || !flock($file, LOCK_EX)) {
                        if ($file) fclose($file);
                        $message = 'Could not lock the .env file for updating.';
                        $messageType = 'danger';
                    } else {
                        rewind($file);
                        $currentContents = stream_get_contents($file);
                        $updated = preg_replace_callback('/^WHATSG_TEMPLATES_JSON\\s*=.*$/m', function () use ($newLine) { return $newLine; }, (string)$currentContents, 1, $replaceCount);
                        if ($replaceCount === 0) $updated = rtrim((string)$currentContents, "\r\n") . PHP_EOL . $newLine . PHP_EOL;
                        rewind($file);
                        $written = ftruncate($file, 0) && fwrite($file, $updated) === strlen($updated) && fflush($file);
                        flock($file, LOCK_UN);
                        fclose($file);
                        if (!$written) {
                            $message = 'The template list could not be saved to .env.';
                            $messageType = 'danger';
                        } else {
                            $message = $operation === 'delete' ? 'Template removed.' : 'Approved template added.';
                            putenv('WHATSG_TEMPLATES_JSON=' . $json);
                            $_ENV['WHATSG_TEMPLATES_JSON'] = $json;
                        }
                    }
                }
            }
        }
    }
}

include "include/header.php";
?>
<section role="main" class="content-body">
  <header class="page-header">
    <h2>WhatsApp Templates</h2>
    <div class="right-wrapper pull-right">
      <ol class="breadcrumbs"><li><a href="<?=$siteUrl;?>"><i class="fa fa-home"></i></a></li><li><span>WhatsApp Templates</span></li></ol>
      <a>&nbsp;</a>
    </div>
  </header>

  <section class="panel">
    <header class="panel-heading"><h2 class="panel-title">Add an approved template</h2></header>
    <div class="panel-body">
      <?php if ($message !== ''): ?>
        <div class="alert alert-<?=htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($message, ENT_QUOTES, 'UTF-8');?></div>
      <?php endif; ?>
      <p>Enter the exact template name approved by Meta. Variables are optional. If the template has placeholders, add them in order: the first fills <code>{{1}}</code>, the second fills <code>{{2}}</code>, and so on. A template without placeholders is saved with an empty <code>exampleArr</code>.</p>
      <form method="post" class="form-horizontal">
        <input type="hidden" name="csrfToken" value="<?=htmlspecialchars($_SESSION['whatsAppTemplatesCsrf'], ENT_QUOTES, 'UTF-8');?>">
        <input type="hidden" name="operation" value="add">
        <div class="form-group">
          <label class="col-md-2 control-label" for="templateLabel">Dropdown label</label>
          <div class="col-md-8"><input class="form-control" id="templateLabel" name="label" maxlength="100" required placeholder="Shipment schedule"></div>
        </div>
        <div class="form-group">
          <label class="col-md-2 control-label" for="templateName">Approved template name</label>
          <div class="col-md-8"><input class="form-control" id="templateName" name="templateName" maxlength="150" required placeholder="shipment_schedule"></div>
        </div>
        <div class="form-group">
          <label class="col-md-2 control-label" for="supportsMedia">Media header</label>
          <div class="col-md-8"><label class="checkbox-inline"><input type="checkbox" id="supportsMedia" name="supportsMedia" value="1"> This approved template supports an image header</label><p class="help-block">For task messages, the first attached image is sent as <code>mediaUri</code>. If no image is attached, <code>mediaUri</code> is omitted.</p></div>
        </div>
        <div class="form-group">
          <label class="col-md-2 control-label">Template variables</label>
          <div class="col-md-8">
            <div id="templateVariables">
              <div class="row template-variable-row" style="margin-bottom:8px">
                <div class="col-sm-9"><select class="form-control" name="variables[]">
                  <option value="">Select variable for {{1}}</option>
                  <?php foreach ($mergeTags as $mergeTag): ?><option value="<?=htmlspecialchars($mergeTag, ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars('{' . $mergeTag . '}', ENT_QUOTES, 'UTF-8');?></option><?php endforeach; ?>
                </select></div>
                <div class="col-sm-3"><button type="button" class="btn btn-danger remove-variable">Remove</button></div>
              </div>
            </div>
            <button type="button" class="btn btn-default" id="addVariable"><i class="fa fa-plus"></i> Add variable</button>
            <p class="help-block" id="emptyVariablesNotice" style="display:none">No variables selected. This is okay for templates without placeholders.</p>
            <p class="help-block">Each selection adds one value to <code>exampleArr</code> in order. Add the variables used by the approved template.</p>
          </div>
        </div>
        <div class="form-group">
          <div class="col-md-8 col-md-offset-2"><button type="submit" class="btn btn-primary">Save template</button> <a class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=add">Back to tasks</a></div>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <header class="panel-heading"><h2 class="panel-title">Configured templates</h2></header>
    <div class="panel-body">
      <?php if ($templates): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead><tr><th>Dropdown label</th><th>Approved template name</th><th>Media header</th><th>Variables in order</th><th>Remove</th></tr></thead>
          <tbody>
          <?php foreach ($templates as $templateLabel => $templateConfig): ?>
            <tr>
              <td><?=htmlspecialchars((string)$templateLabel, ENT_QUOTES, 'UTF-8');?></td>
              <td><?=htmlspecialchars((string)$templateConfig['name'], ENT_QUOTES, 'UTF-8');?></td>
              <td><?=whatsgTemplateSupportsMedia($templateConfig) ? 'Image' : 'No';?></td>
              <td><?=htmlspecialchars(implode(' → ', array_map(function ($variable) { return '{' . $variable . '}'; }, $templateConfig['variables'])), ENT_QUOTES, 'UTF-8');?></td>
              <td>
                <form method="post" onsubmit="return confirm('Remove this WhatsApp template?');">
                  <input type="hidden" name="csrfToken" value="<?=htmlspecialchars($_SESSION['whatsAppTemplatesCsrf'], ENT_QUOTES, 'UTF-8');?>">
                  <input type="hidden" name="operation" value="delete">
                  <input type="hidden" name="deleteLabel" value="<?=htmlspecialchars((string)$templateLabel, ENT_QUOTES, 'UTF-8');?>">
                  <button type="submit" class="btn btn-xs btn-danger">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?><p class="text-muted">No WhatsApp templates are configured.</p><?php endif; ?>
    </div>
  </section>
</section>
<template id="variableRowTemplate">
  <div class="row template-variable-row" style="margin-bottom:8px">
    <div class="col-sm-9"><select class="form-control" name="variables[]">
      <option value="">Select variable</option>
      <?php foreach ($mergeTags as $mergeTag): ?><option value="<?=htmlspecialchars($mergeTag, ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars('{' . $mergeTag . '}', ENT_QUOTES, 'UTF-8');?></option><?php endforeach; ?>
    </select></div>
    <div class="col-sm-3"><button type="button" class="btn btn-danger remove-variable">Remove</button></div>
  </div>
</template>
<script>
(function () {
  var rows = document.getElementById('templateVariables');
  var add = document.getElementById('addVariable');
  var rowTemplate = document.getElementById('variableRowTemplate');
  function renumber() {
    var selects = rows.querySelectorAll('select[name="variables[]"]');
    document.getElementById('emptyVariablesNotice').style.display = selects.length === 0 ? '' : 'none';
    for (var i = 0; i < selects.length; i++) {
      selects[i].options[0].text = 'Select variable for {{' + (i + 1) + '}}';
    }
  }
  add.addEventListener('click', function () {
    rows.appendChild(rowTemplate.content.cloneNode(true));
    renumber();
  });
  rows.addEventListener('click', function (event) {
    if (event.target.classList.contains('remove-variable')) {
      event.target.closest('.template-variable-row').remove();
      renumber();
    }
  });
})();
</script>
<?php include "include/footer.php"; ?>
