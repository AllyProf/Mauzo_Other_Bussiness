<?php

namespace App\Http\Controllers;

use App\Services\BusinessWebsiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PublicBusinessSiteController extends Controller
{
    public function __construct(private BusinessWebsiteService $websites)
    {
    }

    public function home(string $slug)
    {
        return $this->render($slug, 'home');
    }

    public function page(string $slug, string $page)
    {
        if (! in_array($page, \App\Models\BusinessWebsite::PAGE_KEYS, true) || $page === 'home') {
            abort(404);
        }

        return $this->render($slug, $page);
    }

    public function submitContact(Request $request, string $slug)
    {
        $website = $this->websites->findPublishedBySlug($slug);
        if (! $website || ! $website->page('contact')['show_contact_form']) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:2000',
        ]);

        $business = $website->business;
        $to = $business->email;

        if ($to) {
            try {
                Mail::raw(
                    "Contact form — {$website->publicUrl('contact')}\n\n"
                    ."Name: {$data['name']}\n"
                    .'Phone: '.($data['phone'] ?: '—')."\n\n"
                    .$data['message'],
                    fn ($mail) => $mail->to($to)->subject('Website contact: '.$business->name)
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Thank you — we received your message and will respond soon.');
    }

    private function render(string $slug, string $page)
    {
        $website = $this->websites->findPublishedBySlug($slug);
        if (! $website) {
            abort(404);
        }

        $business = $website->business;
        $content = $website->page($page);
        if ($content === []) {
            $content = $this->websites->defaultPages($business)[$page] ?? [];
        }

        $social = $website->social_links ?? [];
        $whatsapp = $social['whatsapp'] ?? $business->phone ?? '';
        $whatsappDigits = preg_replace('/\D+/', '', (string) $whatsapp);

        $view = match ($page) {
            'home' => 'public-site.home',
            'products' => 'public-site.products',
            default => 'public-site.page',
        };

        $catalogSettings = $content;
        if ($page === 'home') {
            $productsContent = $website->page('products');
            if ($productsContent === []) {
                $productsContent = $this->websites->defaultPages($business)['products'] ?? [];
            }
            $catalogSettings = $productsContent;
        }

        $catalog = in_array($page, ['home', 'products'], true)
            ? $this->websites->productCatalog($business, $catalogSettings)
            : collect();

        return view($view, [
            'website' => $website,
            'business' => $business,
            'page' => $page,
            'content' => $content,
            'catalog' => $catalog,
            'nav' => $this->navItems($website, $page),
            'whatsappDigits' => $whatsappDigits,
            'whatsappDisplay' => $whatsapp,
        ]);
    }

    /**
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    private function navItems($website, string $currentPage): array
    {
        $items = [];
        foreach (['home' => 'Home', 'products' => 'Products', 'about' => 'About', 'contact' => 'Contact', 'order' => 'Order'] as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'url' => $website->publicUrl($key),
                'active' => $key === $currentPage,
            ];
        }

        return $items;
    }
}
