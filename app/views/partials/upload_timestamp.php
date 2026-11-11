<span class="upload-timestamp">
    <span>Upload date: <?= e(upload_date_label($paper)) ?></span><br>
    <span class="text-small">Recorded: <?= e(timestamp_label($paper['created_at'] ?? null)) ?></span>
</span>
