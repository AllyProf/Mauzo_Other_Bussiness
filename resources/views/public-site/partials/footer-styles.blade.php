<style>
  #footer.site-footer {
    position: relative;
    padding: 0;
    color: rgba(255, 255, 255, .85);
    font-size: 14px;
    background: #111 url("{{ asset('gp-assets/img/footer-bg-pos.jpg') }}") center / cover no-repeat !important;
  }
  #footer.site-footer { --footer-brand-light: color-mix(in srgb, var(--footer-brand), #fff 45%); }
  #footer.site-footer::before { content: ""; position: absolute; inset: 0; background: rgba(0, 0, 0, .78); }
  .site-footer__wave { position: relative; display: block; width: 100%; height: 60px; z-index: 1; }
  .site-footer__body { position: relative; z-index: 1; padding-top: 50px; }
  .site-footer__logo { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 16px; text-decoration: none; }
  .site-footer__logo img { width: 58px; height: 58px; object-fit: contain; padding: 6px; border-radius: 8px; background: #fff; }
  .site-footer__logo span { color: #fff; font-family: 'Raleway', sans-serif; font-weight: 800; font-size: 20px; }
  .site-footer__about { color: rgba(255, 255, 255, .75); line-height: 1.7; }
  .site-footer__col { padding-left: 24px; border-left: 1px solid rgba(255, 255, 255, .12); }
  #footer .site-footer__heading {
    position: relative; margin-bottom: 22px; padding-bottom: 10px;
    color: var(--footer-brand-light); font-family: 'Poppins', sans-serif;
    font-style: italic; font-weight: 600; font-size: 17px;
  }
  #footer .site-footer__heading::after { content: ""; position: absolute; left: 0; bottom: 0; width: 42px; height: 3px; border-radius: 2px; background: var(--footer-brand); }
  .site-footer__list { list-style: none; padding: 0; margin: 0; }
  .site-footer__list li { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
  .site-footer__list li i {
    flex: 0 0 26px; width: 26px; height: 26px; margin-top: 2px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%; background: var(--footer-brand); color: #fff; font-size: 12px;
  }
  .site-footer__links li i { flex-basis: auto; width: auto; height: auto; background: none; color: var(--footer-brand-light); font-size: 14px; }
  .site-footer__list a, .site-footer__list span { color: rgba(255, 255, 255, .85); text-decoration: none; word-break: break-word; }
  .site-footer__list a:hover { color: var(--footer-brand-light); }
  .site-footer__socials { display: flex; flex-wrap: wrap; gap: 12px; }
  .site-footer__social {
    display: inline-flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; background: var(--social-color, var(--footer-brand));
    color: #fff !important; font-size: 22px; box-shadow: 0 4px 12px rgba(0, 0, 0, .35);
  }
  .site-footer__social--instagram { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285aeb 90%); }
  .site-footer__bottom { margin-top: 40px; padding: 22px 0 26px; border-top: 1px solid color-mix(in srgb, var(--footer-brand) 50%, transparent); text-align: center; }
  .site-footer__bottom p { margin: 0; }
  .site-footer__bottom a { color: var(--footer-brand-light); font-weight: 600; text-decoration: none; }
  .site-footer__powered { color: rgba(255, 255, 255, .6); }
  .site-footer__sep { margin: 0 10px; color: color-mix(in srgb, var(--footer-brand-light) 70%, transparent); }
  @media (max-width: 991px) {
    .site-footer__col { border-left: 0; padding-left: calc(var(--bs-gutter-x) * .5); }
  }
</style>
