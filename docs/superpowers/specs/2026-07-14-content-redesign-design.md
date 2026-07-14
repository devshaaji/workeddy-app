# Content Module Page Redesign Specification

We are redesigning the general content page (`page.php` and `preview.php`) to resemble the article layout structure of the Claude Help Center, focusing on premium typography, generous spacing, a two-column desktop layout with a sticky Table of Contents (TOC) on the right, and active-state highlighting on scroll.

## Visual & Structural Goals

1. **High-Readability Typography**: Serif titles/headings (e.g. Georgia, custom serif styles), larger margins above headings, clean system-ui body text with `line-height: 1.75`.
2. **Desktop Grid Layout**:
   - Left Column: Title, metadata, content body sections.
   - Right Column: Sticky Table of Contents (`.content-toc`) with a left border line and dynamic active state highlighting.
3. **Metadata Info**: Inline header metadata (Updated date, estimated reading time, audience tags) rather than a boxy top-card.
4. **Scroll-Spy TOC**: Highlight links in the TOC dynamically as sections scroll into view.

## Proposed Changes

### Stylesheet

#### [NEW] `public/assets/css/modules/content-page.css`
Contains styling for:
- `.content-article-layout`: CSS grid / flex container.
- Typography overrides for headings and paragraphs within article bodies.
- `.content-toc`: Sticky positioning, continuous vertical line, active state border highlight.

### Script

#### [NEW] `public/assets/js/modules/content-page.js`
Handles:
- IntersectionObserver and scroll spy tracking to toggle `.is-active` class on TOC branch links.
- Smooth scrolling behaviors when clicking on TOC links.

### Views

#### [MODIFY] `modules/Content/Presentation/Views/page.php`
- Integrate layout CSS and JS via `$pageCss` and `$pageScripts`.
- Render the new header metadata section.
- Construct the two-column grid structure (Left Column for content, Right Column for TOC).
- Incorporate Table of Contents list based on page sections.

#### [MODIFY] `modules/Content/Presentation/Views/preview.php`
- Mirror structural changes of `page.php` to ensure previews look exactly like the published articles.

#### [MODIFY] `modules/Content/Presentation/Views/Partials/render_sections.php`
- Clean up card-based designs for paragraphs/lists. Render section titles and blocks inline with spacious, elegant typography instead of individual boxy cards.

## Spec Self-Review
- Checked for place-holders (none).
- Verified compatibility with the main template shell (`app.php`) which supports `$pageCss` and `$pageScripts`.
- The design is scoped specifically to `page.php`, `preview.php`, and their shared partial `render_sections.php`.
