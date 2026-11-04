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

        $website = $this->websites->findPublishedBySlug($slug);
        if (! $website) {
            abort(404);
        }

        // One-page site: old /site/{slug}/about URLs land on the home section.
        return redirect()->to($website->publicUrl($page), 301);
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

        return redirect()
            ->to($website->publicUrl('contact'))
            ->with('success', 'Thank you — we received your message and will respond soon.');
    }

    private function render(string $slug, string $page)
    {
        $website = $this->websites->findPublishedBySlug($slug);
        if (! $website) {
            abort(404);
        }

        $business = $website->business;
        $defaults = $this->websites->defaultPages($business);

        $content = $website->page('home');
        if ($content === []) {
            $content = $defaults['home'] ?? [];
        }

        $sections = [];
        foreach (['products', 'about', 'contact', 'order'] as $key) {
            $section = $website->page($key);
            $sections[$key] = $section !== [] ? $section : ($defaults[$key] ?? []);
        }

        $social = $website->social_links ?? [];
        $whatsapp = $social['whatsapp'] ?? $business->phone ?? '';
        $whatsappDigits = preg_replace('/\D+/', '', (string) $whatsapp);

        $catalog = $this->websites->productCatalog($business, $sections['products']);

        return view('public-site.home', [
            'website' => $website,
            'business' => $business,
            'page' => 'home',
            'content' => $content,
            'sections' => $sections,
            'catalog' => $catalog,
            'nav' => $this->navItems($website),
            'whatsappDigits' => $whatsappDigits,
            'whatsappDisplay' => $whatsapp,
        ]);
    }

    /**
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    private function navItems($website): array
    {
        $items = [];
        foreach (['home' => 'Home', 'products' => 'Products', 'contact' => 'Contact', 'order' => 'Order'] as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'url' => $key === 'home' ? $website->publicUrl().'#hero' : $website->publicUrl($key),
                'active' => false,
            ];
        }

        return $items;
    }
}
