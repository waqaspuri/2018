<?php
include "include/sessionAdmin.php";
include "include/configure.php";
require_once __DIR__ . '/../php/mail_config.php';
require_once __DIR__ . '/../php/whatsapp_tasks.php';

$mergeTags = whatsgTaskMergeTagNames();
$templates = whatsgTaskTemplates($conn);
$editLabel = (string)($_GET['edit'] ?? '');
$editTemplate = isset($templates[$editLabel]) ? $templates[$editLabel] : null;
$duplicateLabel = (string)($_GET['duplicate'] ?? '');
$duplicateTemplate = !$editTemplate && isset($templates[$duplicateLabel]) ? $templates[$duplicateLabel] : null;
$formTemplate = $editTemplate ?: $duplicateTemplate;
$formLabel = $editTemplate ? $editLabel : ($duplicateTemplate ? 'Copy of ' . $duplicateLabel : '');
$formVariables = $formTemplate ? array_values($formTemplate['variables']) : array('');
if (!$formVariables) $formVariables = array('');
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
    } else {
        $operation = (string)($_POST['operation'] ?? 'add');
        $originalLabel = trim((string)($_POST['originalLabel'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        if ($operation === 'delete') {
            $label = (string)($_POST['deleteLabel'] ?? '');
            if (!array_key_exists($label, $templates)) {
                $message = 'Select a template to remove.';
                $messageType = 'danger';
            } elseif (whatsgTaskDeleteTemplate($conn, $label)) {
                $message = 'Template removed.';
                $templates = whatsgTaskTemplates($conn);
            } else {
                $message = 'The template could not be removed from the database.';
                $messageType = 'danger';
            }
        } else {
            $templateName = trim((string)($_POST['templateName'] ?? ''));
            $mediaType = (string)($_POST['mediaType'] ?? '');
            if (!in_array($mediaType, array('', 'image', 'document'), true)) $mediaType = '';
            $variables = isset($_POST['variables']) && is_array($_POST['variables']) ? array_values($_POST['variables']) : array();
            $variables = array_values(array_map('trim', $variables));
            $variables = array_values(array_filter($variables, function ($variable) { return $variable !== ''; }));
            if ($label === '' || $templateName === '') {
                $message = 'Enter a dropdown label and the exact approved template name.';
                $messageType = 'danger';
            } elseif ($operation === 'update' && !isset($templates[$originalLabel])) {
                $message = 'The template to edit could not be found. Reload the page and try again.';
                $messageType = 'danger';
            } elseif ($operation !== 'update' && isset($templates[$label])) {
                $message = 'The template dropdown label "' . $label . '" is already in use. Choose a different label, or remove the existing template first.';
                $messageType = 'danger';
            } elseif (count(array_diff($variables, array_merge($mergeTags, array('custom')))) > 0) {
                $message = 'One or more selected variables are not supported.';
                $messageType = 'danger';
            } elseif (preg_match('/[\r\n\x00-\x1F]/', $label . $templateName)) {
                $message = 'Labels and template names cannot contain line breaks or control characters.';
                $messageType = 'danger';
            } else {
                $saved = $operation === 'update'
                    ? ($originalLabel !== '' && whatsgTaskUpdateTemplate($conn, $originalLabel, $templateName, $variables, $mediaType))
                    : whatsgTaskSaveTemplate($conn, $label, $templateName, $variables, $mediaType);
                if ($saved) {
                    $message = $operation === 'update' ? 'Template updated.' : 'Approved template added.';
                    $templates = whatsgTaskTemplates($conn);
                    $editLabel = $operation === 'update' ? $originalLabel : '';
                    $editTemplate = isset($templates[$editLabel]) ? $templates[$editLabel] : null;
                    $duplicateLabel = '';
                    $duplicateTemplate = null;
                    $formTemplate = $editTemplate;
                    $formLabel = $editLabel;
                    $formVariables = $formTemplate ? array_values($formTemplate['variables']) : array('');
                    if (!$formVariables) $formVariables = array('');
                } elseif (isset($templates[$label])) {
                    $message = 'The template dropdown label "' . $label . '" is already in use. Choose a different label, or remove the existing template first.';
                    $messageType = 'danger';
                } else {
                    $message = 'The template could not be saved to the database.';
                    $messageType = 'danger';
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
    <header class="panel-heading"><h2 class="panel-title"><?=$editTemplate ? 'Edit approved template' : ($duplicateTemplate ? 'Preview duplicate template' : 'Add an approved template');?></h2></header>
    <div class="panel-body">
      <?php if ($message !== ''): ?>
        <div class="alert alert-<?=htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($message, ENT_QUOTES, 'UTF-8');?></div>
      <?php endif; ?>
      <?php if ($duplicateTemplate): ?><div class="alert alert-info">This is a preview of a copy of <strong><?=htmlspecialchars($duplicateLabel, ENT_QUOTES, 'UTF-8');?></strong>. It will not be added to the database unless you save it.</div><?php endif; ?>
      <p>Enter the exact template name approved by Meta. Variables are optional. If the template has placeholders, add them in order: the first fills <code>{{1}}</code>, the second fills <code>{{2}}</code>, and so on. A template without placeholders is saved with an empty <code>exampleArr</code>.</p>
      <form method="post" class="form-horizontal">
        <input type="hidden" name="csrfToken" value="<?=htmlspecialchars($_SESSION['whatsAppTemplatesCsrf'], ENT_QUOTES, 'UTF-8');?>">
        <input type="hidden" name="operation" value="<?=$editTemplate ? 'update' : 'add';?>">
        <?php if ($editTemplate): ?><input type="hidden" name="originalLabel" value="<?=htmlspecialchars($editLabel, ENT_QUOTES, 'UTF-8');?>"><?php endif; ?>
        <div class="form-group">
          <label class="col-md-2 control-label" for="templateLabel">Dropdown label</label>
          <div class="col-md-8"><input class="form-control" id="templateLabel" name="label" maxlength="100" required placeholder="Shipment schedule" value="<?=htmlspecialchars($formLabel, ENT_QUOTES, 'UTF-8');?>" <?=$editTemplate ? 'readonly' : '';?>></div>
        </div>
        <div class="form-group">
          <label class="col-md-2 control-label" for="templateName">Approved template name</label>
          <div class="col-md-8"><input class="form-control" id="templateName" name="templateName" maxlength="150" required placeholder="shipment_schedule" value="<?=htmlspecialchars((string)($formTemplate['name'] ?? ''), ENT_QUOTES, 'UTF-8');?>"></div>
        </div>
        <div class="form-group">
          <label class="col-md-2 control-label" for="mediaType">Media header</label>
          <div class="col-md-8"><select class="form-control" id="mediaType" name="mediaType"><option value="" <?=!$formTemplate || whatsgTemplateMediaType($formTemplate) === '' ? 'selected' : '';?>>No media header</option><option value="image" <?=whatsgTemplateMediaType($formTemplate ?? array()) === 'image' ? 'selected' : '';?>>Image</option><option value="document" <?=whatsgTemplateMediaType($formTemplate ?? array()) === 'document' ? 'selected' : '';?>>Document (PDF/ZIP)</option></select><p class="help-block">Choose the media type configured in the approved WhatsApp template. Task messages use the first matching attached file as <code>mediaUri</code>. Image headers require an image template; PDF and ZIP headers use a document template.</p></div>
        </div>
        <div class="form-group">
          <label class="col-md-2 control-label">Template variables</label>
          <div class="col-md-8">
            <div id="templateVariables">
              <?php foreach ($formVariables as $variableIndex => $formVariable): ?><div class="row template-variable-row" style="margin-bottom:8px">
                <div class="col-sm-9"><select class="form-control" name="variables[]">
                  <option value="">Select variable for {{<?=$variableIndex + 1;?>}}</option>
                  <?php foreach ($mergeTags as $mergeTag): ?><option value="<?=htmlspecialchars($mergeTag, ENT_QUOTES, 'UTF-8');?>" <?=$formVariable === $mergeTag ? 'selected' : '';?>><?=htmlspecialchars('{' . $mergeTag . '}', ENT_QUOTES, 'UTF-8');?></option><?php endforeach; ?>
                  <option value="custom" <?=$formVariable === 'custom' ? 'selected' : '';?>>Assign Custom {{<?=$variableIndex + 1;?>}}</option>
                </select></div>
                <div class="col-sm-3"><button type="button" class="btn btn-danger remove-variable">Remove</button></div>
              </div><?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-default" id="addVariable"><i class="fa fa-plus"></i> Add variable</button>
            <p class="help-block" id="emptyVariablesNotice" style="display:none">No variables selected. This is okay for templates without placeholders.</p>
            <p class="help-block">Each selection adds one value to <code>exampleArr</code> in order. Add the variables used by the approved template.</p>
          </div>
        </div>
        <div class="form-group">
          <div class="col-md-8 col-md-offset-2"><button type="submit" class="btn btn-primary"><?=$editTemplate ? 'Save changes' : 'Save template';?></button> <?php if ($editTemplate): ?><a class="btn btn-default" href="whatsapp_templates.php">Cancel edit</a> <?php endif; ?><a class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=add">Back to tasks</a></div>
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
          <thead><tr><th>Dropdown label</th><th>Approved template name</th><th>Media header</th><th>Variables in order</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($templates as $templateLabel => $templateConfig): ?>
            <tr>
              <td><?=htmlspecialchars((string)$templateLabel, ENT_QUOTES, 'UTF-8');?></td>
              <td><?=htmlspecialchars((string)$templateConfig['name'], ENT_QUOTES, 'UTF-8');?></td>
              <td><?php $configuredMediaType = whatsgTemplateMediaType($templateConfig); echo $configuredMediaType === 'document' ? 'Document (PDF/ZIP)' : ($configuredMediaType === 'image' ? 'Image' : 'No'); ?></td>
              <td><?=htmlspecialchars(implode(' → ', array_map(function ($variable) { return $variable === 'custom' ? 'Custom' : '{' . $variable . '}'; }, $templateConfig['variables'])), ENT_QUOTES, 'UTF-8');?></td>
              <td><a class="btn btn-xs btn-default" href="whatsapp_templates.php?edit=<?=rawurlencode((string)$templateLabel);?>">Edit</a>
                <a class="btn btn-xs btn-info" href="whatsapp_templates.php?duplicate=<?=rawurlencode((string)$templateLabel);?>">Duplicate</a>
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
      <option value="custom">Assign Custom {{1}}</option>
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
      for (var optionIndex = 0; optionIndex < selects[i].options.length; optionIndex++) {
        if (selects[i].options[optionIndex].value === 'custom') {
          selects[i].options[optionIndex].text = 'Assign Custom {{' + (i + 1) + '}}';
        }
      }
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
