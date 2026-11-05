<?php
use App\Helpers\Role;
$guideRole = Role::canonical((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
$uploadMb = \App\Helpers\FileUpload::maxMb();
?>
<dialog id="guide-modal" class="preview-modal guide-modal" aria-labelledby="guide-modal-heading">
    <div class="preview-modal-head">
        <h2 id="guide-modal-heading">Guide</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="guide-modal-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <?php if ($guideRole === Role::STUDENT): ?>
        <h3>How to upload a study</h3>
        <ol class="guide-steps">
            <li>Open <strong>Upload</strong> next to Home.</li>
            <li>Type the manuscript title, short title, and abstract. Marked fields with <span class="req">*</span> are required.</li>
            <li>Add every author. Use <strong>Add author</strong> for group members.</li>
            <li>Choose the study type, school year, and your research adviser.</li>
            <li>Attach the manuscript as PDF or DOCX (up to <?= (int) $uploadMb ?> MB).</li>
            <li>Use <strong>Save as draft</strong> to keep it, or <strong>Pass to adviser</strong> to start review.</li>
        </ol>
        <h3>What happens next</h3>
        <ol class="guide-steps">
            <li>Your adviser checks the paper first.</li>
            <li>After that, send it from <strong>Waiting</strong> to the dept research coordinator.</li>
            <li>Then it goes Dean → School Research Coordinator → Librarian.</li>
            <li>Watch progress on <strong>Track</strong>. If a revision is requested, open the study and upload a new file.</li>
        </ol>
        <p class="muted">Home lists published papers in your department. Other students see title and abstract only, not the full file.</p>
    <?php elseif ($guideRole === Role::RESEARCH_ADVISER): ?>
        <h3>What to do</h3>
        <ol class="guide-steps">
            <li>Open <strong>Home</strong> to see papers in your department. You can open the full file for those papers.</li>
            <li>Open <strong>Review</strong> for advisee submissions waiting on you.</li>
            <li>Read the manuscript, then approve or ask for a revision.</li>
            <li>After you approve, the student sends it to the dept research coordinator.</li>
        </ol>
        <p class="muted">Other departments show title and abstract only.</p>
    <?php elseif ($guideRole === Role::DEPT_RESEARCH_COORDINATOR): ?>
        <h3>What to do</h3>
        <ol class="guide-steps">
            <li>Open <strong>Home</strong>. Full files are available for every paper in your department.</li>
            <li>Use All / Pending / Accepted to find studies waiting on you.</li>
            <li>Check the manuscript, then send cleared papers to the dean.</li>
        </ol>
        <p class="muted">Other departments stay title and abstract only.</p>
    <?php elseif ($guideRole === Role::DEAN): ?>
        <h3>What to do</h3>
        <ol class="guide-steps">
            <li>Open <strong>Home</strong> for department papers with full files.</li>
            <li>Open <strong>Review</strong> for studies the dept research coordinator forwarded.</li>
            <li>Check the paper, then send cleared studies to the school research coordinator.</li>
        </ol>
        <p class="muted">Other departments stay title and abstract only.</p>
    <?php elseif ($guideRole === Role::SCHOOL_RESEARCH_COORDINATOR): ?>
        <h3>What to do</h3>
        <ol class="guide-steps">
            <li>Open <strong>Home</strong> for school-wide papers. Full files are available.</li>
            <li>Record ethics, format, and plagiarism checks.</li>
            <li>Send cleared studies to the librarian.</li>
        </ol>
    <?php elseif ($guideRole === Role::SCHOOL_ADMINISTRATOR): ?>
        <h3>What to do</h3>
        <ol class="guide-steps">
            <li>Open <strong>Home</strong> for school-wide papers. Full files are available.</li>
            <li>Open the review queue and publish studies the school research coordinator already cleared.</li>
            <li>Use <strong>People</strong> to create accounts. Use <strong>Logs</strong> to see who downloaded a file.</li>
        </ol>
    <?php else: ?>
        <p>Use Home to open your dashboard, then follow the buttons for your role.</p>
    <?php endif; ?>
    <h3>Review path</h3>
    <p class="guide-path">Student → Research Adviser → Dept Research Coordinator → Dean → School Research Coordinator → Librarian</p>
</dialog>
