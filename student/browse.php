<?php
    session_start();
    require '../config/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: ../auth/login.php");
        exit();
    }

    $notif_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $notif_count_stmt->bind_param("i", $_SESSION['user_id']);
    $notif_count_stmt->execute();
    $notif_count = $notif_count_stmt->get_result()->fetch_assoc()['count'];

    // Load this student's saved service IDs for the favorite controls in the listing.
    // Load this student's saved service IDs for the favorite controls in the listing.
    $favorites_stmt = $conn->prepare("SELECT service_id FROM favorites WHERE student_id = ?");
    $favorites_stmt->bind_param("i", $_SESSION['user_id']);
    $favorites_stmt->execute();
    $favorite_service_ids = [];
    $favorites_result = $favorites_stmt->get_result();
    while ($favorite = $favorites_result->fetch_assoc()) {
        $favorite_service_ids[(int) $favorite['service_id']] = true;
    }

    $categories_result = $conn->query("SELECT * FROM categories");

    $selected_category = isset($_GET['category_id']) ? $_GET['category_id'] : '';
    $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

    // Browsing shows approved, available listings and combines their average rating and review count.
    // Browsing shows approved, available listings and combines their average rating and review count.
    $sql = "SELECT services.service_id, services.title, services.description, categories.category_name, 
                   users.full_name, users.phone_number, users.email, COALESCE(AVG(reviews.rating), 0) AS average_rating,
                   COUNT(reviews.review_id) AS rating_count,
                   MAX(CASE WHEN reviews.student_id = " . (int) $_SESSION['user_id'] . " THEN reviews.rating END) AS user_rating
            FROM services
            JOIN categories ON services.category_id = categories.category_id
            JOIN users ON services.provider_id = users.user_id
            LEFT JOIN reviews ON reviews.service_id = services.service_id
            WHERE services.availability_status = 'available' AND services.status = 'approved'";

    if (!empty($selected_category)) {
        $sql .= " AND services.category_id = ?";
    }
    if (!empty($keyword)) {
        $sql .= " AND (services.title LIKE ? OR services.description LIKE ?)";
    }

    $sql .= " GROUP BY services.service_id, services.title, services.description, categories.category_name, users.full_name, users.phone_number, users.email";
    $stmt = $conn->prepare($sql);
    if (!empty($selected_category) && !empty($keyword)) {
        $search_term = "%" . $keyword . "%";
        $stmt->bind_param("iss", $selected_category, $search_term, $search_term);
    } elseif (!empty($selected_category)) {
        $stmt->bind_param("i", $selected_category);
    } elseif (!empty($keyword)) {
        $search_term = "%" . $keyword . "%";
        $stmt->bind_param("ss", $search_term, $search_term);
    }
    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="app-shell">

    <div class="app-sidebar-overlay" id="sidebar-overlay"></div>

    <div class="app-sidebar">
        <button type="button" class="app-sidebar-close-btn" aria-label="Close navigation menu">&times;</button>
        <div class="brand">
            <div class="split-login-mark" style="width:28px;height:28px;font-size:12px;">S</div>
            SSMS
        </div>
        <a href="dashboard.php">Dashboard</a>
        <a href="browse.php" class="active">Browse Services</a>
        <a href="favorites.php">My Favorites</a>
        <a href="notifications.php">Notifications <?php if ($notif_count > 0): ?><span class="badge bg-danger"><?php echo $notif_count; ?></span><?php endif; ?></a>
        <div class="logout-link">
            <form action="../auth/logout.php" method="post" class="logout-form"><?php echo csrf_field(); ?><button type="submit" class="logout-form-button">Logout</button></form>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">Browse Services</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">

            <?php if (isset($_GET['rating'])): ?>
                <div class="alert <?php echo $_GET['rating'] === 'saved' ? 'alert-success' : 'alert-danger'; ?>">
                    <?php
                        if ($_GET['rating'] === 'saved') {
                            echo 'Your rating has been saved.';
                        } elseif ($_GET['rating'] === 'limited') {
                            echo 'You have submitted ratings too quickly. Please wait a minute before trying again.';
                        } else {
                            echo 'Your rating could not be saved. Please choose an available service and a rating from 1 to 5.';
                        }
                    ?>
                </div>
            <?php endif; ?>

            <form action="browse.php" method="GET" class="row g-3 mb-4" id="service-filter-form">
                <div class="col-md-4">
                    <select name="category_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php 
                            $categories_result->data_seek(0);
                            while ($cat = $categories_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                <?php if ($selected_category == $cat['category_id']) echo 'selected'; ?>>
                                <?php echo $cat['category_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-5 position-relative">
                    <input type="text" name="keyword" id="keyword-search" class="form-control"
                           placeholder="Search by keyword..." 
                           value="<?php echo htmlspecialchars($keyword); ?>">
                    <div id="keyword-suggestions" class="list-group position-absolute w-100 shadow-sm" style="z-index: 10; display: none;"></div>
                </div>
                <div class="col-md-3">
                    <button type="submit" id="keyword-search-button" class="btn btn-brand w-100">Search</button>
                </div>
            </form>

            <div id="results-container">
                <?php if ($result->num_rows === 0): ?>
                    <div class="listing-empty">
                        <span class="listing-empty-icon" aria-hidden="true">⌕</span>
                        <p>No services found matching your search.</p>
                    </div>
                <?php else: ?>
                    <div class="listing-grid service-browse-grid">
                    <?php while ($service = $result->fetch_assoc()): ?>
                        <article class="listing-card">
                            <div class="listing-card-header">
                                <span class="listing-badge listing-badge-category"><?php echo htmlspecialchars($service['category_name']); ?></span>
                            </div>
                            <h2 class="listing-title"><?php echo htmlspecialchars($service['title']); ?></h2>
                            <p class="listing-description"><?php echo htmlspecialchars($service['description']); ?></p>
                            <div class="service-rating" aria-label="Average rating <?php echo number_format((float) $service['average_rating'], 1); ?> out of 5">
                                <span class="service-rating-stars">★</span>
                                <strong><?php echo $service['rating_count'] ? number_format((float) $service['average_rating'], 1) : 'New'; ?></strong>
                                <span><?php echo (int) $service['rating_count']; ?> <?php echo (int) $service['rating_count'] === 1 ? 'rating' : 'ratings'; ?></span>
                            </div>
                            <button type="button" class="listing-action-btn service-details-toggle" aria-expanded="false">View details</button>
                            <div class="service-card-details" hidden>
                            <div class="listing-meta">
                                <div class="listing-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg><span><?php echo htmlspecialchars($service['full_name']); ?></span></div>
                                <div class="listing-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.64a2 2 0 0 1-.45 2.11L8.01 9.74a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.86.29 1.74.5 2.64.62A2 2 0 0 1 22 16.92z"></path></svg><span><?php echo htmlspecialchars($service['phone_number']); ?></span></div>
                            </div>
                            <div class="listing-actions">
                                <?php if (isset($favorite_service_ids[(int) $service['service_id']])): ?>
                                    <span class="listing-action-btn listing-action-saved" aria-label="Already saved to favorites">✓ Saved to Favorites</span>
                                <?php else: ?>
                                    <form action="favorite_action.php" method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>"><button type="submit" class="listing-action-btn">Save to Favorites</button></form>
                                <?php endif; ?>
                            </div>
                            <form action="rate_service.php" method="post" class="rating-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>">
                                <span class="rating-form-label"><?php echo $service['user_rating'] ? 'Update your rating:' : 'Rate this service:'; ?></span>
                                <div class="rating-options">
                                    <?php for ($star = 1; $star <= 5; $star++): ?>
                                        <label><input type="radio" name="rating" value="<?php echo $star; ?>" <?php echo (int) $service['user_rating'] === $star ? 'checked' : ''; ?> required><span><?php echo $star; ?>★</span></label>
                                    <?php endfor; ?>
                                </div>
                                <button type="submit" class="listing-action-btn">Save rating</button>
                            </form>
                            </div>
                        </article>
                    <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Search API responses are rendered below this page without a full reload. -->
<script src="../assets/js/script.js"></script>
<script>
const csrfToken = <?php echo json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('service-filter-form');
    const input = document.getElementById('keyword-search');
    const searchButton = document.getElementById('keyword-search-button');
    const resultsContainer = document.getElementById('results-container');
    const suggestionsContainer = document.getElementById('keyword-suggestions');
    const initialResults = resultsContainer.innerHTML;
    let debounceTimer;
    let activeRequest;

    document.querySelectorAll('.service-details-toggle').forEach((toggle) => {
        const details = toggle.parentElement.querySelector('.service-card-details');
        toggle.addEventListener('click', () => {
            details.hidden = !details.hidden;
            toggle.textContent = details.hidden ? 'View details' : 'Hide details';
            toggle.setAttribute('aria-expanded', String(!details.hidden));
        });
    });

    const addTextElement = (parent, tagName, className, value) => {
        const element = document.createElement(tagName);
        element.className = className;
        element.textContent = value || 'Not provided';
        parent.appendChild(element);
    };

    const renderServices = (services) => {
        resultsContainer.replaceChildren();

        if (services.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'listing-empty';
            empty.innerHTML = '<span class="listing-empty-icon" aria-hidden="true">⌕</span><p>No services found matching your search.</p>';
            resultsContainer.appendChild(empty);
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'listing-grid service-browse-grid';

        services.forEach((service) => {
            const card = document.createElement('article');
            card.className = 'listing-card';

            const header = document.createElement('div');
            header.className = 'listing-card-header';
            addTextElement(header, 'span', 'listing-badge listing-badge-category', service.category_name);
            card.appendChild(header);
            addTextElement(card, 'h2', 'listing-title', service.title);
            addTextElement(card, 'p', 'listing-description', service.description);

            const rating = document.createElement('div');
            rating.className = 'service-rating';
            const ratingCount = Number(service.rating_count || 0);
            const averageRating = Number(service.average_rating || 0);
            rating.setAttribute('aria-label', `Average rating ${averageRating.toFixed(1)} out of 5`);
            rating.innerHTML = `<span class="service-rating-stars">★</span><strong>${ratingCount ? averageRating.toFixed(1) : 'New'}</strong><span>${ratingCount} ${ratingCount === 1 ? 'rating' : 'ratings'}</span>`;
            card.appendChild(rating);

            const detailsToggle = document.createElement('button');
            detailsToggle.type = 'button';
            detailsToggle.className = 'listing-action-btn service-details-toggle';
            detailsToggle.textContent = 'View details';
            detailsToggle.setAttribute('aria-expanded', 'false');
            card.appendChild(detailsToggle);

            const details = document.createElement('div');
            details.className = 'service-card-details';
            details.hidden = true;
            detailsToggle.addEventListener('click', () => {
                details.hidden = !details.hidden;
                detailsToggle.textContent = details.hidden ? 'View details' : 'Hide details';
                detailsToggle.setAttribute('aria-expanded', String(!details.hidden));
            });

            const meta = document.createElement('div');
            meta.className = 'listing-meta';
            addTextElement(meta, 'div', 'listing-meta-item', `Provider: ${service.full_name || 'Not provided'}`);
            addTextElement(meta, 'div', 'listing-meta-item', `Phone: ${service.phone_number || 'Not provided'}`);
            details.appendChild(meta);

            const actions = document.createElement('div');
            actions.className = 'listing-actions';
            if (Number(service.is_favorite)) {
                const saved = document.createElement('span');
                saved.className = 'listing-action-btn listing-action-saved';
                saved.textContent = '✓ Saved to Favorites';
                actions.appendChild(saved);
            } else {
                const favoriteForm = document.createElement('form');
                favoriteForm.action = 'favorite_action.php';
                favoriteForm.method = 'post';
                const favoriteToken = document.createElement('input');
                favoriteToken.type = 'hidden'; favoriteToken.name = 'csrf_token'; favoriteToken.value = csrfToken;
                const favoriteService = document.createElement('input');
                favoriteService.type = 'hidden'; favoriteService.name = 'service_id'; favoriteService.value = service.service_id;
                const favoriteButton = document.createElement('button');
                favoriteButton.type = 'submit'; favoriteButton.className = 'listing-action-btn'; favoriteButton.textContent = 'Save to Favorites';
                favoriteForm.append(favoriteToken, favoriteService, favoriteButton);
                actions.appendChild(favoriteForm);
            }
            details.appendChild(actions);

            const ratingForm = document.createElement('form');
            ratingForm.action = 'rate_service.php';
            ratingForm.method = 'post';
            ratingForm.className = 'rating-form';
            const serviceId = document.createElement('input');
            serviceId.type = 'hidden'; serviceId.name = 'service_id'; serviceId.value = service.service_id;
            ratingForm.appendChild(serviceId);
            const ratingToken = document.createElement('input');
            ratingToken.type = 'hidden'; ratingToken.name = 'csrf_token'; ratingToken.value = csrfToken;
            ratingForm.appendChild(ratingToken);
            const label = document.createElement('span');
            label.className = 'rating-form-label';
            label.textContent = Number(service.user_rating) ? 'Update your rating:' : 'Rate this service:';
            ratingForm.appendChild(label);
            const options = document.createElement('div');
            options.className = 'rating-options';
            for (let star = 1; star <= 5; star += 1) {
                const starLabel = document.createElement('label');
                const radio = document.createElement('input');
                radio.type = 'radio'; radio.name = 'rating'; radio.value = star; radio.required = true;
                radio.checked = Number(service.user_rating) === star;
                const starText = document.createElement('span'); starText.textContent = `${star}★`;
                starLabel.append(radio, starText); options.appendChild(starLabel);
            }
            ratingForm.appendChild(options);
            const saveRating = document.createElement('button');
            saveRating.type = 'submit'; saveRating.className = 'listing-action-btn'; saveRating.textContent = 'Save rating';
            ratingForm.appendChild(saveRating);
            details.appendChild(ratingForm);
            card.appendChild(details);
            grid.appendChild(card);
        });

        resultsContainer.appendChild(grid);
    };

    const renderSuggestions = (services) => {
        suggestionsContainer.replaceChildren();
        if (services.length === 0 || input.value.trim() === '') {
            suggestionsContainer.style.display = 'none';
            return;
        }

        services.slice(0, 5).forEach((service) => {
            const suggestion = document.createElement('button');
            suggestion.type = 'button';
            suggestion.className = 'list-group-item list-group-item-action text-start';
            suggestion.textContent = `${service.title} — ${service.category_name}`;
            suggestion.addEventListener('click', () => {
                input.value = service.title;
                suggestionsContainer.style.display = 'none';
                form.submit();
            });
            suggestionsContainer.appendChild(suggestion);
        });
        suggestionsContainer.style.display = 'block';
    };

    const searchServices = async () => {
        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();

        try {
            const response = await fetch(`search_api.php?keyword=${encodeURIComponent(input.value)}`, {
                signal: activeRequest.signal,
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Search request failed');
            }

            const services = await response.json();
            renderSuggestions(services);
            renderServices(services);
        } catch (error) {
            if (error.name !== 'AbortError') {
                suggestionsContainer.style.display = 'none';
            }
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        if (input.value.trim() === '') {
            suggestionsContainer.replaceChildren();
            suggestionsContainer.style.display = 'none';
            resultsContainer.innerHTML = initialResults;
            return;
        }
        debounceTimer = setTimeout(searchServices, 250);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.position-relative')) {
            suggestionsContainer.style.display = 'none';
        }
    });
});
</script>

</body>
</html>
