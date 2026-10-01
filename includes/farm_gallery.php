<?php

$farmGallerySlides = [
    [
        'image' => 'assets/farm-gallery/rice-paddy.jpg',
        'alt' => 'Green paddy crop growing in Eluru, Andhra Pradesh',
        'title' => 'Read the rice field',
        'category' => 'RICE CROP',
        'text' => 'Rice changes quickly through its growth stages. Regular checks make it easier to notice water stress, discoloration, and pest damage early.',
        'tips' => [
            'Inspect leaves and stems in several parts of the field.',
            'Record crop stage and affected area during each check.'
        ],
        'credit' => 'Photo: iMahesh · CC BY-SA 4.0',
        'source' => 'https://commons.wikimedia.org/wiki/File:Paddy_crop_near_Malkapuram,_Eluru.jpg'
    ],
    [
        'image' => 'assets/farm-gallery/coconut-farm.jpg',
        'alt' => 'Coconut farm in Kadakola village, India',
        'title' => 'Check the coconut canopy',
        'category' => 'COCONUT FARM',
        'text' => 'Look over the crown, trunk, and fruit bunches. Note yellowing, holes, wilting, or damaged fruit and compare changes over time.',
        'tips' => [
            'Check several trees rather than one isolated plant.',
            'Use the same observation points on follow-up visits.'
        ],
        'credit' => 'Photo: Prof tpms · CC BY-SA 4.0',
        'source' => 'https://commons.wikimedia.org/wiki/File:Coconut_farm_at_Kadakola_village.jpg'
    ],
    [
        'image' => 'assets/farm-gallery/rice-hispa.jpg',
        'alt' => 'Rice hispa beetle, a leaf-feeding rice pest',
        'title' => 'Recognize pest signs',
        'category' => 'PEST WATCH',
        'text' => 'Rice hispa larvae mine leaves and adults scrape leaf surfaces. Similar symptoms can have other causes, so confirm the pattern before choosing a response.',
        'tips' => [
            'Inspect both sides of leaves and check nearby plants.',
            'Record the pest or symptom and affected area.'
        ],
        'credit' => 'Photo: gbohne / Guido Bohne · CC BY-SA 2.0',
        'source' => 'https://commons.wikimedia.org/wiki/File:Rice_Hispa_(6282950178).jpg'
    ],
    [
        'image' => 'assets/farm-gallery/crop-treatment.jpg',
        'alt' => 'A farmer applying a crop treatment in a field',
        'title' => 'Treat crops carefully',
        'category' => 'CROP TREATMENT',
        'text' => 'Use chemical treatments only when they are approved for the crop and problem. Follow the product label and local agricultural guidance.',
        'tips' => [
            'Wear the protective equipment required by the label.',
            'Keep people, animals, and waterways away as directed.'
        ],
        'credit' => 'Photo: Evelyn Simak · CC BY-SA 2.0',
        'source' => 'https://commons.wikimedia.org/wiki/File:Farmer_spraying_his_crop,_Ten_Mile_Bank_-_geograph.org.uk_-_1894500.jpg'
    ],
    [
        'image' => 'assets/farm-gallery/field-inspection.jpg',
        'alt' => 'A farmer inspecting rice fields in Apac, Uganda',
        'title' => 'Prevent problems early',
        'category' => 'FIELD PREVENTION',
        'text' => 'Frequent field walks and clear records help farmers spot changes before they spread and make follow-up advice more useful.',
        'tips' => [
            'Keep tools and field edges clean; remove crop debris when advised.',
            'Recheck affected areas and ask an extension worker when uncertain.'
        ],
        'credit' => 'Photo: Bram Jacobs · CC BY-SA 4.0',
        'source' => 'https://commons.wikimedia.org/wiki/File:Inspection_of_rice_fields_in_Apac,_Uganda.jpg'
    ]
];

$gallerySlidesJson = json_encode(
    $farmGallerySlides,
    JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
$galleryVariant = $farmGalleryVariant ?? 'farmer';
$firstGallerySlide = $farmGallerySlides[0];
?>

<section class="farm-gallery-section <?= e($galleryVariant) ?>">
    <div class="farm-gallery-shell">
        <div class="farm-gallery-heading">
            <div>
                <span class="farm-gallery-kicker">FIELD GUIDE · <?= count($farmGallerySlides) ?> PHOTOS</span>
                <h2>See it. Scout it. Protect it.</h2>
                <p>Crop examples, pest signs, safer treatment, and prevention steps.</p>
            </div>
            <a class="farm-gallery-download" href="assets/farmer-field-guide-images.zip" download>
                <span aria-hidden="true">↓</span> Download all pictures
            </a>
        </div>

        <div class="farm-gallery" data-slides="<?= e($gallerySlidesJson) ?>" role="region" aria-roledescription="carousel" aria-label="Farmer crop and pest field guide">
            <figure class="farm-gallery-visual">
                <img class="farm-gallery-image" src="<?= e($firstGallerySlide['image']) ?>" alt="<?= e($firstGallerySlide['alt']) ?>" fetchpriority="low">
                <figcaption class="farm-gallery-credit">
                    <a href="<?= e($firstGallerySlide['source']) ?>" target="_blank" rel="noopener noreferrer"><?= e($firstGallerySlide['credit']) ?></a>
                </figcaption>
            </figure>

            <div class="farm-gallery-copy" aria-live="polite" aria-atomic="true">
                <div class="farm-gallery-copy-top">
                    <span class="farm-gallery-category"><?= e($firstGallerySlide['category']) ?></span>
                    <span class="farm-gallery-count"><span data-gallery-current>01</span> / <?= count($farmGallerySlides) ?></span>
                </div>
                <h3 data-gallery-title><?= e($firstGallerySlide['title']) ?></h3>
                <p data-gallery-text><?= e($firstGallerySlide['text']) ?></p>
                <ul data-gallery-tips>
                    <?php foreach ($firstGallerySlide['tips'] as $tip): ?>
                        <li><?= e($tip) ?></li>
                    <?php endforeach; ?>
                </ul>

                <div class="farm-gallery-controls">
                    <button type="button" data-gallery-prev aria-label="Previous field guide image" title="Previous">
                        <span aria-hidden="true">&larr;</span>
                    </button>
                    <div class="farm-gallery-dots" aria-label="Choose a field guide image">
                        <?php foreach ($farmGallerySlides as $index => $slide): ?>
                            <button type="button" data-gallery-dot="<?= $index ?>" aria-label="Show image <?= $index + 1 ?>: <?= e($slide['title']) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" data-gallery-next aria-label="Next field guide image" title="Next">
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                    <button type="button" data-gallery-toggle aria-label="Pause slideshow" title="Pause slideshow">
                        <span aria-hidden="true">&#x23F8;</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="assets/farm-gallery.js" defer></script>
