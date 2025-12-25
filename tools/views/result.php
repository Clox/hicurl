<h2>Result</h2>

<?php if (!empty($responseData['error']) || !empty($responseData['errorCode'])): ?>
	<div>
		<strong>Error:</strong> <?= htmlspecialchars((string)$responseData['error'], ENT_QUOTES) ?><br>
		<strong>Error Code:</strong> <?= htmlspecialchars((string)$responseData['errorCode'], ENT_QUOTES) ?>
	</div>
<?php endif; ?>

<?php if (isset($responseData['headers'])): ?>
	<div>
		<h3>Response Headers</h3>
		<pre style="white-space:pre-wrap;"><?= htmlspecialchars(print_r($responseData['headers'], true), ENT_QUOTES) ?></pre>
	</div>
<?php endif; ?>

<?php if (array_key_exists('content', $responseData)): ?>
	<div>
		<h3>Response Body</h3>
		<pre style="white-space:pre-wrap;"><?= htmlspecialchars(print_r($responseData['content'], true), ENT_QUOTES) ?></pre>
	</div>
<?php endif; ?>

<hr>
