    </main>
    <footer class="site-footer">
        <p><a href="<?= escape(websiteContentValue($websiteContent, 'footer_collective_link', '/')) ?>"><?= nl2br(escape(websiteContentValue($websiteContent, 'footer_collective_name', "Everyday People\nArt Collective"))) ?></a></p>
        <p><?= escape(websiteContentValue($websiteContent, 'footer_supporting_text', 'Art, stories, and updates.')) ?></p>
        <p><?= escape(websiteContentValue($websiteContent, 'footer_closing_statement', 'Make room for the unfinished.')) ?></p>
        <p>&copy; <?= date('Y') ?> <?= escape(websiteContentValue($websiteContent, 'footer_copyright_statement', 'Everyday People Art Collective. All rights reserved. Reproduction without permission is prohibited.')) ?></p>
    </footer>
</div>
</body>
</html>
