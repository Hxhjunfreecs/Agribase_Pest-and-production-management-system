<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| ADMIN: CREATE / DELETE ADVISORY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    user()['role'] === 'admin'
) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your session expired. Please refresh the page and try again.');
        redirect('advisories.php');
    }

    if (isset($_POST['delete'])) {

        $advisoryId =
            (int) $_POST['delete'];

        $stmt = $pdo->prepare("
            DELETE FROM advisories
            WHERE id = ?
        ");

        $stmt->execute([
            $advisoryId
        ]);

        flash(
            'success',
            'Advisory deleted successfully.'
        );

    } elseif (isset($_POST['publish'])) {
        $advisoryId = (int) $_POST['publish'];
        $stmt = $pdo->prepare("
            SELECT title, crop, risk_level, content
            FROM advisories
            WHERE id = ?
              AND status = 'Draft'
            LIMIT 1
        ");
        $stmt->execute([$advisoryId]);
        $draft = $stmt->fetch();

        if ($draft) {
            $update = $pdo->prepare("
                UPDATE advisories
                SET status = 'Published'
                WHERE id = ?
                  AND status = 'Draft'
            ");
            $update->execute([$advisoryId]);

            if ($update->rowCount() > 0) {
                $emailMessage = $draft['title'] . "\nCrop: " . $draft['crop'] . "\nRisk: " . $draft['risk_level'] . "\n\n" . $draft['content'];
                notifyAgriBaseAll(
                    $pdo,
                    'New AgriBase agricultural advisory',
                    $emailMessage
                );
                flash('success', 'Advisory published successfully.');
            } else {
                flash('error', 'This draft has already been published.');
            }
        } else {
            flash('error', 'Draft advisory not found.');
        }
    } else {

        $title = trim(
            $_POST['title'] ?? ''
        );

        $crop = trim(
            $_POST['crop'] ?? ''
        );

        $risk = trim(
            $_POST['risk_level'] ?? 'Low'
        );

        $content = trim(
            $_POST['content'] ?? ''
        );

        $status = trim(
            $_POST['status'] ?? 'Draft'
        );

        if (
            $title === '' ||
            $content === ''
        ) {
            flash(
                'error',
                'Please complete the advisory title and content.'
            );

            redirect('advisories.php');
        }

        $allowedRisk = [
            'Low',
            'Moderate',
            'High'
        ];

        $allowedStatus = [
            'Published',
            'Draft'
        ];

        if (!in_array(
            $risk,
            $allowedRisk,
            true
        )) {
            $risk = 'Low';
        }

        if (!in_array(
            $status,
            $allowedStatus,
            true
        )) {
            $status = 'Draft';
        }

        $stmt = $pdo->prepare("
            INSERT INTO advisories (
                title,
                crop,
                risk_level,
                content,
                status,
                created_by
            )
            VALUES (
                ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $title,
            $crop,
            $risk,
            $content,
            $status,
            user()['id']
        ]);

        if ($status === 'Published') {
            $emailMessage =
                $title . "\nCrop: " . $crop . "\nRisk: " . $risk . "\n\n" . $content;

            notifyAgriBaseAll(
                $pdo,
                'New AgriBase agricultural advisory',
                $emailMessage
            );
        }

        flash(
            'success',
            'Advisory saved successfully.'
        );
    }

    redirect('advisories.php');
}


/*
|--------------------------------------------------------------------------
| LOAD ADVISORIES
|--------------------------------------------------------------------------
*/

if (user()['role'] === 'admin') {

    $stmt = $pdo->query("
        SELECT
            a.*,
            u.full_name AS created_by_name

        FROM advisories a

        INNER JOIN users u
            ON u.id = a.created_by

        ORDER BY a.created_at DESC
    ");

} else {

    $stmt = $pdo->query("
        SELECT
            a.*,
            u.full_name AS created_by_name

        FROM advisories a

        INNER JOIN users u
            ON u.id = a.created_by

        WHERE a.status = 'Published'

        ORDER BY a.created_at DESC
    ");
}

$advisories = $stmt->fetchAll();
$publishedAdvisories = array_values(array_filter(
    $advisories,
    static fn(array $advisory): bool => $advisory['status'] === 'Published'
));
$draftAdvisories = array_values(array_filter(
    $advisories,
    static fn(array $advisory): bool => $advisory['status'] === 'Draft'
));


layoutHeader(
    'Agricultural Advisories',
    'advisories'
);

?>

<div class="page-head">

    <div>

        <h1>
            Agricultural Advisories
        </h1>

        <p>
            Decision-support guidance connected to
            rice and coconut monitoring conditions.
        </p>

    </div>

</div>


<?php if (user()['role'] === 'admin'): ?>

    <button class="btn advisory-compose-trigger" type="button" id="open-advisory-composer">
        + New advisory
    </button>

    <dialog class="advisory-composer-dialog" id="advisory-composer" aria-labelledby="advisory-composer-title">
    <section class="advisory-create card">

        <div class="advisory-section-heading">
            <span class="advisory-kicker">EDITORIAL DESK</span>
            <h2 id="advisory-composer-title">Create Advisory</h2>
            <p>Publish practical agricultural guidance for farmers.</p>
        </div>

        <button class="advisory-composer-close" type="button" id="close-advisory-composer" aria-label="Close advisory form">&times;</button>

        <form method="post">
            <?= csrfField() ?>

            <div class="form-grid">

                <div class="field">
                    <label for="title">Title</label>
                    <input id="title" name="title" maxlength="180" required>
                </div>

                <div class="field">
                    <label for="crop">Crop</label>
                    <select id="crop" name="crop" required>
                        <option value="Rice">Rice</option>
                        <option value="Coconut">Coconut</option>
                        <option value="Rice & Coconut">Rice &amp; Coconut</option>
                    </select>
                </div>

                <div class="field">
                    <label for="risk_level">Risk Level</label>
                    <select id="risk_level" name="risk_level">
                        <option value="Low">Low</option>
                        <option value="Moderate">Moderate</option>
                        <option value="High">High</option>
                    </select>
                </div>

                <div class="field full">
                    <label for="content">Advisory Content</label>
                    <textarea id="content" name="content" required></textarea>
                </div>

                <div class="field full">
                    <label>Save or publish</label>
                    <div class="advisory-form-actions">
                        <button class="btn secondary" type="submit" name="status" value="Draft">Save as Draft</button>
                        <button class="btn" type="submit" name="status" value="Published">Publish Now</button>
                    </div>
                </div>

            </div>

        </form>

    </section>
    </dialog>

    <script>
        const advisoryComposer = document.getElementById('advisory-composer');
        document.getElementById('open-advisory-composer').addEventListener('click', () => advisoryComposer.showModal());
        document.getElementById('close-advisory-composer').addEventListener('click', () => advisoryComposer.close());
        advisoryComposer.addEventListener('click', event => {
            if (event.target === advisoryComposer) advisoryComposer.close();
        });
    </script>

<?php endif; ?>

<section class="advisory-list advisory-subsection">

    <div class="advisory-section-heading">
        <span class="advisory-kicker">FIELD GUIDANCE</span>
        <h2>Published Advisories</h2>
        <p>Current guidance available to farmers.</p>
    </div>

    <div class="advisory-feed">

    <?php foreach ($publishedAdvisories as $advisory): ?>

        <article class="advisory-post">

            <div class="advisory-post-author">
                <span class="advisory-post-avatar" aria-hidden="true">A</span>
                <div>
                    <strong>AgriBase</strong>
                    <span>Agricultural guidance</span>
                </div>
            </div>

            <div class="advisory-post-meta">
                <?= e($advisory['crop']) ?>
                <?php if (user()['role'] === 'admin'): ?>
                    · <?= e($advisory['risk_level']) ?>
                <?php endif; ?>
            </div>

            <h2>
                <a class="advisory-title-link" href="advisory_detail.php?id=<?= (int) $advisory['id'] ?>">
                    <?= e($advisory['title']) ?>
                </a>
            </h2>

            <?php if ($advisory['image_path']): ?>
                <a href="advisory_detail.php?id=<?= (int) $advisory['id'] ?>" aria-label="View picture and full details for <?= e($advisory['title']) ?>">
                    <img class="advisory-list-image" src="<?= e($advisory['image_path']) ?>" alt="<?= e($advisory['image_alt'] ?: $advisory['title']) ?>">
                </a>
            <?php endif; ?>

            <p>
                <?= nl2br(
                    e($advisory['content'])
                ) ?>
            </p>

            <div class="advisory-post-date">
                <?= e(
                    date(
                        'M d, Y',
                        strtotime(
                            $advisory['created_at']
                        )
                    )
                ) ?>
            </div>

            <a class="btn small secondary advisory-open-link" href="advisory_detail.php?id=<?= (int) $advisory['id'] ?>">
                <?= user()['role'] === 'admin' ? 'View / Edit Details' : 'View Full Advisory' ?>
            </a>


            <?php if (user()['role'] === 'admin'): ?>

                <form
                    method="post"
                    style="margin-top:14px"
                >
                    <?= csrfField() ?>

                    <button
                        class="btn small secondary"
                        type="submit"
                        name="delete"
                        value="<?= $advisory['id'] ?>"
                    >
                        Delete
                    </button>

                </form>

            <?php endif; ?>

        </article>

    <?php endforeach; ?>


    <?php if (!$publishedAdvisories): ?>

        <div class="card">

            <p>
                No published advisories are available yet.
            </p>

        </div>

    <?php endif; ?>

    </div>

</section>

<?php if (user()['role'] === 'admin'): ?>
    <section class="advisory-list advisory-subsection">
        <div class="advisory-section-heading">
            <span class="advisory-kicker">INTERNAL</span>
            <h2>Draft Advisories</h2>
            <p>Drafts are visible only to administrators.</p>
        </div>

        <div class="advisory-feed">
            <?php foreach ($draftAdvisories as $advisory): ?>
                <article class="advisory-post advisory-draft-card">
                    <div class="advisory-post-author">
                        <span class="advisory-post-avatar" aria-hidden="true">A</span>
                        <div>
                            <strong>AgriBase</strong>
                            <span>Administrator draft</span>
                        </div>
                    </div>
                    <div class="advisory-post-meta">
                        <?= e($advisory['crop']) ?> · <?= e($advisory['risk_level']) ?>
                    </div>
                    <h2><a class="advisory-title-link" href="advisory_detail.php?id=<?= (int) $advisory['id'] ?>"><?= e($advisory['title']) ?></a></h2>
                    <p><?= nl2br(e($advisory['content'])) ?></p>
                    <div class="advisory-post-date">
                        Draft · <?= e(date('M d, Y', strtotime($advisory['created_at']))) ?>
                    </div>
                    <form method="post" class="advisory-card-actions">
                        <?= csrfField() ?>
                        <button class="btn" type="submit" name="publish" value="<?= (int) $advisory['id'] ?>">Publish Now</button>
                        <button class="btn secondary small" type="submit" name="delete" value="<?= (int) $advisory['id'] ?>">Delete</button>
                    </form>
                </article>
            <?php endforeach; ?>

            <?php if (!$draftAdvisories): ?>
                <div class="card">
                    <p>No draft advisories.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>


<?php

layoutFooter();
?>
