<?php
/**
 * Left panel of the login and signup pages.
 * Expects $authHeadline, $authIntro and $authImage from the page that includes it.
 */
?>
<aside class="auth-panel" aria-hidden="true">
    <div class="auth-panel-copy">
        <h2><?php echo $authHeadline; ?></h2>
        <p><?php echo $authIntro; ?></p>
    </div>
    <img src="<?php echo htmlspecialchars($authImage); ?>" alt="" class="auth-panel-image">
</aside>
