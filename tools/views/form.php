<?php
$methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
$method = $requestSpec['method'];
$settings = $requestSpec['settings'];
$history = $requestSpec['history'];
?>

<h1>Hicurl Request Workbench</h1>
<form method="post">
	<div>
		<label for="url">URL</label><br>
		<input type="text" id="url" name="url" value="<?= htmlspecialchars($requestSpec['url'], ENT_QUOTES) ?>" required style="width:100%;">
	</div>

	<div>
		<label for="method">Method</label><br>
		<select id="method" name="method">
			<?php foreach ($methods as $option): ?>
				<option value="<?= $option ?>" <?= $option === $method ? 'selected' : '' ?>><?= $option ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div>
		<label for="headers">Headers (one per line: Header: value)</label><br>
		<textarea id="headers" name="headers" style="width:100%;height:120px;font-family:monospace;"><?= htmlspecialchars($requestSpec['headersRaw'], ENT_QUOTES) ?></textarea>
	</div>

	<div>
		<label for="body">Body</label><br>
		<textarea id="body" name="body" style="width:100%;height:160px;font-family:monospace;"><?= htmlspecialchars($requestSpec['body'], ENT_QUOTES) ?></textarea>
	</div>

	<div>
		<label for="xpath">XPath (one expression per line)</label><br>
		<textarea id="xpath" name="xpath" style="width:100%;height:100px;font-family:monospace;"><?= htmlspecialchars(implode("\n", $settings['xpath']), ENT_QUOTES) ?></textarea>
	</div>

	<div>
		<label><input type="checkbox" name="jsonPayload" <?= $settings['jsonPayload'] ? 'checked' : '' ?>> jsonPayload</label><br>
		<label><input type="checkbox" name="jsonResponse" <?= $settings['jsonResponse'] ? 'checked' : '' ?>> jsonResponse</label><br>
		<label><input type="checkbox" name="retryOnNull" <?= $settings['retryOnNull'] ? 'checked' : '' ?>> retryOnNull</label><br>
		<label><input type="checkbox" name="retryOnIncompleteHTML" <?= $settings['retryOnIncompleteHTML'] ? 'checked' : '' ?>> retryOnIncompleteHTML</label><br>
		<label><input type="checkbox" name="history_enabled" <?= $history['enabled'] ? 'checked' : '' ?>> History enabled</label><br>
		<label for="history_name">History name (optional)</label><br>
		<input type="text" id="history_name" name="history_name" value="<?= htmlspecialchars((string)$history['name'], ENT_QUOTES) ?>" style="width:100%;">
	</div>

	<div>
		<button type="submit">Send Request</button>
	</div>
</form>
<hr>
