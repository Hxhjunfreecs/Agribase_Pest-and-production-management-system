<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

requireLogin();

$advisoryId = (int) ($_GET['id'] ?? 0);
$isAdmin = user()['role'] === 'admin';

$stmt = $pdo->prepare("
    SELECT a.*, u.full_name AS created_by_name
    FROM advisories a
    INNER JOIN users u ON u.id = a.created_by
    WHERE a.id = ?
");
$stmt->execute([$advisoryId]);
$advisory = $stmt->fetch();

if (!$advisory || (!$isAdmin && $advisory['status'] !== 'Published')) {
    http_response_code(404);
    exit('Advisory not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your session expired. Please refresh the page and try again.');
        redirect('advisory_detail.php?id=' . $advisoryId);
    }

    $title = trim($_POST['title'] ?? '');
    $crop = trim($_POST['crop'] ?? '');
    $risk = $_POST['risk_level'] ?? 'Low';
    $content = trim($_POST['content'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $status = $_POST['status'] ?? $advisory['status'];
    $imageAlt = trim($_POST['image_alt'] ?? '');
    $imagePath = $advisory['image_path'];

    if ($title === '' || $crop === '' || $content === '') {
        flash('error', 'Title, crop, and advisory content are required.');
        redirect('advisory_detail.php?id=' . $advisoryId);
    }

    if (!in_array($risk, ['Low', 'Moderate', 'High'], true)) {
        $risk = 'Low';
    }
    if (!in_array($status, ['Published', 'Draft'], true)) {
        $status = $advisory['status'];
    }

    if (!empty($_POST['remove_image'])) {
        if ($imagePath && str_starts_with($imagePath, 'assets/advisory-')) {
            $oldImage = __DIR__ . '/' . $imagePath;
            if (is_file($oldImage)) {
                unlink($oldImage);
            }
        }
        $imagePath = null;
        $imageAlt = '';
    }

    $upload = $_FILES['image'] ?? null;
    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
            flash('error', 'Choose an image no larger than 5 MB.');
            redirect('advisory_detail.php?id=' . $advisoryId);
        }

        $imageInfo = @getimagesize($upload['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        $mime = $imageInfo['mime'] ?? '';
        if (!$imageInfo || !isset($extensions[$mime])) {
            flash('error', 'Upload a valid JPEG, PNG, or WebP image.');
            redirect('advisory_detail.php?id=' . $advisoryId);
        }

        $filename = 'advisory-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destination = __DIR__ . '/assets/' . $filename;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            flash('error', 'The image could not be saved. Check the assets folder permissions.');
            redirect('advisory_detail.php?id=' . $advisoryId);
        }

        if ($imagePath && str_starts_with($imagePath, 'assets/advisory-')) {
            $oldImage = __DIR__ . '/' . $imagePath;
            if (is_file($oldImage)) {
                unlink($oldImage);
            }
        }
        $imagePath = 'assets/' . $filename;
    }

    $update = $pdo->prepare("
        UPDATE advisories
        SET title = ?, crop = ?, risk_level = ?, content = ?, details = ?,
            image_path = ?, image_alt = ?, status = ?
        WHERE id = ?
    ");
    $update->execute([
        $title,
        $crop,
        $risk,
        $content,
        $details !== '' ? $details : null,
        $imagePath,
        $imagePath ? $imageAlt : null,
        $status,
        $advisoryId
    ]);

    if ($advisory['status'] === 'Draft' && $status === 'Published') {
        notifyAgriBaseAll(
            $pdo,
            'New AgriBase agricultural advisory',
            $title . "\nCrop: " . $crop . "\nRisk: " . $risk . "\n\n" . $content . "\n\n" . $details
        );
    }

    flash('success', 'Advisory updated successfully.');
    redirect('advisory_detail.php?id=' . $advisoryId);
}

layoutHeader('Advisory Details', 'advisories');
?>

<div class="page-head">
    <div>
        <span class="advisory-kicker"><?= e($advisory['crop']) ?><?php if ($isAdmin): ?> · <?=e($advisory['risk_level'])?><?php endif; ?></span>
        <h1><?= e($advisory['title']) ?></h1>
        <p><?= e($advisory['status']) ?> · <?= e(date('M d, Y', strtotime($advisory['created_at']))) ?></p>
    </div>
    <a class="btn secondary" href="advisories.php">Back to Advisories</a>
</div>

<?php if ($isAdmin): ?>
    <section class="card advisory-detail-editor">
        <h2>Edit Advisory</h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="field">
                    <label for="title">Title</label>
                    <input id="title" name="title" maxlength="180" value="<?= e($advisory['title']) ?>" required>
                </div>
                <div class="field">
                    <label for="crop">Crop</label>
                    <select id="crop" name="crop" required>
                        <?php foreach (['Rice', 'Coconut', 'Rice & Coconut'] as $cropOption): ?>
                            <option value="<?= e($cropOption) ?>" <?= $advisory['crop'] === $cropOption ? 'selected' : '' ?>><?= e($cropOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="risk_level">Risk Level</label>
                    <select id="risk_level" name="risk_level">
                        <?php foreach (['Low', 'Moderate', 'High'] as $riskOption): ?>
                            <option value="<?= e($riskOption) ?>" <?= $advisory['risk_level'] === $riskOption ? 'selected' : '' ?>><?= e($riskOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="status">Visibility</label>
                    <select id="status" name="status">
                        <?php foreach (['Draft', 'Published'] as $statusOption): ?>
                            <option value="<?= e($statusOption) ?>" <?= $advisory['status'] === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field full">
                    <label for="content">Advisory Content</label>
                    <textarea id="content" name="content" required><?= e($advisory['content']) ?></textarea>
                </div>
                <div class="field full">
                    <label for="details">Additional Details</label>
                    <textarea id="details" name="details" placeholder="Add steps, timing, safety notes, or other guidance."><?= e($advisory['details'] ?? '') ?></textarea>
                </div>
                <div class="field full">
                    <label for="image">Advisory Picture</label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                    <small>JPEG, PNG, or WebP. Maximum size 5 MB.</small>
                </div>
                <div class="field full">
                    <label for="image_alt">Picture description</label>
                    <input id="image_alt" name="image_alt" maxlength="180" value="<?= e($advisory['image_alt'] ?? '') ?>">
                </div>
                <?php if ($advisory['image_path']): ?>
                    <label class="field full advisory-remove-image">
                        <span><input type="checkbox" name="remove_image" value="1"> Remove current picture</span>
                    </label>
                <?php endif; ?>
            </div>
            <button class="btn" type="submit">Save Advisory</button>
        </form>
    </section>
<?php endif; ?>

<article class="card advisory-detail-content">
    <?php if ($advisory['image_path']): ?>
        <img class="advisory-detail-image" src="<?= e($advisory['image_path']) ?>" alt="<?= e($advisory['image_alt'] ?: $advisory['title']) ?>">
    <?php endif; ?>
    <div class="advisory-post-meta"><?= e($advisory['crop']) ?><?php if ($isAdmin): ?> · <?=e($advisory['risk_level'])?><?php endif; ?></div>
    <h2><?= e($advisory['title']) ?></h2>
    <div class="advisory-detail-body"><?= nl2br(e($advisory['content'])) ?></div>
    <?php if (!empty($advisory['details'])): ?>
        <h3>Additional Details</h3>
        <div class="advisory-detail-body"><?= nl2br(e($advisory['details'])) ?></div>
    <?php endif; ?>
    <p class="advisory-post-date">Prepared by <?= e($advisory['created_by_name']) ?> · <?= e(date('M d, Y', strtotime($advisory['created_at']))) ?></p>
</article>

<?php layoutFooter(); ?>
