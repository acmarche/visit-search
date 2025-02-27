<?php

namespace AcMarche\PivotSearch\Data;

use AcMarche\Pivot\Entities\Offre\Offre;
use AcMarche\Pivot\Entities\Specification\SpecData;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use InvalidArgumentException;
use Symfony\Component\String\Slugger\AsciiSlugger;
use VisitMarche\ThemeTail\Lib\Mailer;
use VisitMarche\ThemeTail\Lib\PostUtils;
use VisitMarche\ThemeTail\Lib\RouterPivot;
use VisitMarche\ThemeTail\Lib\WpRepository;
use WP_Post;

class ElasticData
{
    private WpRepository $wpRepository;
    private AsciiSlugger $slugger;

    public function __construct()
    {
        $this->wpRepository = new WpRepository();
        $this->slugger = new AsciiSlugger('fr_FR');
    }

    /**
     * @param int $language
     *
     * @return DocumentElastic[]
     */
    public function getCategories(string $language = 'fr'): array
    {
        $datas = [];
        $today = new DateTime();

        foreach ($this->wpRepository->getCategoriesFromWp() as $category) {
            $description = '';
            if ($category->description) {
                $description = Cleaner::cleandata($category->description);
            }

            $date = $today->format('Y-m-d');
            $content = $description;

            foreach ($this->getPosts($category->cat_ID) as $documentElastic) {
                $content .= $documentElastic->name;
                $content .= $documentElastic->excerpt;
                $content .= $documentElastic->content;
            }

            $children = $this->wpRepository->getChildrenOfCategory($category->cat_ID);
            $tags = [];
            foreach ($children as $child) {
                $tags[] = $child->name;
            }
            $parent = $this->wpRepository->getParentCategory($category->cat_ID);
            if ($parent) {
                $tags[] = $parent->name;
            }

            $document = new DocumentElastic();
            $document->id = $this->createId($category->cat_ID, 'category');
            $document->name = Cleaner::cleandata($category->name);
            $document->excerpt = $this->slugger->slug($description, ' ')->toString();
            $document->content = $this->slugger->slug($content, ' ')->toString();
            $document->tags = $tags;
            $document->date = $date;
            $document->url = get_category_link($category->cat_ID);
            $document->image = null;

            $datas[] = $document;
        }

        return $datas;
    }

    /**
     * @return DocumentElastic[]
     */
    public function getPosts(int $categoryId = null): array
    {
        $args = [
            'numberposts' => 5000,
            'orderby' => 'post_title',
            'order' => 'ASC',
            'post_status' => 'publish',
        ];

        if ($categoryId) {
            $args['category'] = $categoryId;
        }

        $posts = get_posts($args);
        $datas = [];

        foreach ($posts as $post) {
            if (($document = $this->postToDocumentElastic($post)) !== null) {
                $datas[] = $document;
            } else {
                Mailer::sendError(
                    'update elastic error ',
                    'create document '.$post->post_title,
                );
                //  var_dump($post);
            }
        }

        return $datas;
    }

    /**
     * @return DocumentElastic[]
     */
    public function getOffres(string $language = 'fr'): array
    {
        $datas = [];

        foreach ($this->wpRepository->getCategoriesFromWp() as $category) {
            try {
                $offres = $this->wpRepository->findOffersByCategory($category->cat_ID);
            } catch (NonUniqueResultException|InvalidArgumentException|\Psr\Cache\InvalidArgumentException $e) {
                continue;
            }
            foreach ($offres as $offre) {
                $offre->url = RouterPivot::getUrlOffre($category->term_id, $offre->codeCgt);
                $datas[$offre->codeCgt] = $this->createDocumentElasticFromOffre($offre, $language);
            }
        }

        return $datas;
    }

    public function postToDocumentElastic(WP_Post $post): ?DocumentElastic
    {
        try {
            return $this->createDocumentElasticFromWpPost($post);
        } catch (Exception $exception) {
            Mailer::sendError('update elastic', 'create document '.$post->post_title.' => '.$exception->getMessage());
        }

        return null;
    }

    private function createDocumentElasticFromWpPost(WP_Post $post): DocumentElastic
    {
        [$date, $time] = explode(' ', $post->post_date);
        $categories = [];
        foreach (get_the_category($post->ID) as $category) {
            $categories[] = $category->cat_name;
        }

        $content = get_the_content(null, null, $post);
        $content = apply_filters('the_content', $content);

        $document = new DocumentElastic();
        $document->id = $this->createId($post->ID, 'post');
        $document->name = Cleaner::cleandata($post->post_title);
        $document->excerpt = Cleaner::cleandata($post->post_excerpt);
        $document->content = Cleaner::cleandata($content);
        $document->tags = $categories;
        $document->date = $date;
        $document->url = get_permalink($post->ID);
        $document->image = PostUtils::getImage($post);

        return $document;
    }

    private function createDocumentElasticFromOffre(Offre $offre, string $language): DocumentElastic
    {
        $categories = [];
        foreach ($offre->tags as $tag) {
            $categories[] .= ' '.$tag->labelByLanguage($language);
        }

        $content = '';
        $descriptions = $offre->descriptionsByLanguage($language);
        $offre->description = null;
        if ([] !== $descriptions) {
            $offre->description = $offre->descriptions[0]->value;
            foreach ($descriptions as $description) {
                if($description instanceof SpecData) {
                    if ($description->urn == 'urn:fld:descmarket') {
                        $content .= ' '.$description->value;
                    }
                }
                else {
                    if ($description['urn'] == 'urn:fld:descmarket') {
                        $content .= ' '.$description['value'];
                    }
                }
            }
        }

        $today = new DateTime();
        $document = new DocumentElastic();
        $document->id = $this->createId($offre->codeCgt.'-'.$language, 'offer');
        $document->name = Cleaner::cleandata($offre->nameByLanguage($language));
        if ($offre->description) {
            $document->excerpt = $this->slugger->slug($offre->description, ' ')->toString();
        }
        else {
            $document->excerpt = '';
        }
        $document->content = $this->slugger->slug($content, ' ')->toString();
        $document->tags = $categories;
        $document->date = $today->format('Y-m-d');
        $document->url = $offre->url;
        $document->image = $offre->firstImage();

        return $document;
    }

    private function createId(int|string $postId, string $type): string
    {
        return $type.'_'.$postId;
    }
}
