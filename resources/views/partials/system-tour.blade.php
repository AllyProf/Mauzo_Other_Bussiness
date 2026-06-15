@if(!empty($showSystemTour) && !empty($systemTourSteps))
<link href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css" rel="stylesheet">
<style>
  .mauzo-tour-tooltip.introjs-tooltip {
    max-width: 560px;
    min-width: 360px;
    width: min(560px, calc(100vw - 32px));
    padding: 0;
    border-radius: 10px;
    border: none;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
    font-family: 'Century Gothic', 'Segoe UI', sans-serif;
    overflow: hidden;
  }
  .mauzo-tour-tooltip .introjs-tooltip-header {
    background: linear-gradient(135deg, #940000 0%, #6b0000 100%);
    color: #fff;
    padding: 14px 18px 10px;
  }
  .mauzo-tour-tooltip .introjs-tooltip-title {
    color: #fff;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.35;
    margin: 0;
  }
  .mauzo-tour-tooltip .introjs-tooltipcontent {
    padding: 16px 18px 8px;
    font-size: 0.92rem;
    line-height: 1.55;
    color: #333;
  }
  .mauzo-tour-hero {
    margin: -4px 0 14px;
    border-radius: 8px;
    overflow: hidden;
    background: linear-gradient(180deg, #f8f9fc 0%, #eef1f6 100%);
    border: 1px solid #e8ecf1;
  }
  .mauzo-tour-hero img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 230px;
    object-fit: contain;
    object-position: center;
    padding: 12px 16px 8px;
  }
  .mauzo-tour-tooltip .introjs-tooltiptext p:last-child { margin-bottom: 0; }
  .mauzo-tour-tooltip .introjs-tooltipbuttons {
    border-top: 1px solid #eee;
    padding: 10px 14px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
  }
  .mauzo-tour-tooltip .introjs-skipbutton {
    display: inline-block !important;
    margin-right: auto;
    background: transparent !important;
    color: #6c757d !important;
    border: none !important;
    box-shadow: none !important;
    padding: 7px 10px !important;
    font-weight: 600;
    text-decoration: underline;
  }
  .mauzo-tour-tooltip .introjs-skipbutton:hover {
    color: #940000 !important;
    background: transparent !important;
  }
  .mauzo-tour-tooltip .introjs-tooltipbuttons .introjs-button-group,
  .mauzo-tour-tooltip .introjs-tooltipbuttons > .introjs-prevbutton,
  .mauzo-tour-tooltip .introjs-tooltipbuttons > .introjs-nextbutton,
  .mauzo-tour-tooltip .introjs-tooltipbuttons > .introjs-donebutton {
    margin-left: auto;
  }
  .mauzo-tour-tooltip .introjs-button {
    text-shadow: none;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 7px 14px;
    border: 1px solid transparent;
    box-shadow: none;
  }
  .mauzo-tour-tooltip .introjs-nextbutton,
  .mauzo-tour-tooltip .introjs-donebutton {
    background: #940000 !important;
    color: #fff !important;
    border-color: #940000 !important;
  }
  .mauzo-tour-tooltip .introjs-prevbutton {
    background: #f8f9fa !important;
    color: #333 !important;
    border-color: #dee2e6 !important;
  }
  .mauzo-tour-tooltip .introjs-progress {
    background-color: rgba(255, 255, 255, 0.35);
    height: 4px;
    margin-top: 10px;
    border-radius: 4px;
  }
  .mauzo-tour-tooltip .introjs-progressbar {
    background-color: #fff;
    border-radius: 4px;
  }
  .mauzo-tour-highlight {
    box-shadow: 0 0 0 9999px rgba(15, 15, 20, 0.62), 0 0 0 4px rgba(148, 0, 0, 0.85) !important;
    border-radius: 6px;
  }
  .introjs-helperLayer.mauzo-tour-highlight-layer {
    box-shadow: 0 0 0 9999px rgba(15, 15, 20, 0.62), 0 0 0 4px rgba(148, 0, 0, 0.85) !important;
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js"></script>
<script>
(function () {
  var steps = @json($systemTourSteps);
  var surveyImageUrl = @json(asset('panel-assets/img/survey.png'));
  var completeUrl = @json(route('system-tour.complete'));
  var skipUrl = @json(route('system-tour.skip'));
  var csrfToken = @json(csrf_token());

  function postTour(url) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: '{}',
      credentials: 'same-origin',
    }).catch(function () {});
  }

  jQuery(function () {
    if (typeof introJs === 'undefined' || !steps.length) {
      return;
    }

    var filtered = steps.filter(function (step) {
      if (!step.element) {
        return true;
      }

      return document.querySelector(step.element);
    }).map(function (step) {
      if (step.hero && surveyImageUrl) {
        step.intro = '<div class="mauzo-tour-hero"><img src="' + surveyImageUrl + '" alt="' + @json(__('tour.hero_alt')) + '"></div>' + step.intro;
      }

      return step;
    });

    if (!filtered.length) {
      postTour(skipUrl);
      return;
    }

    var finished = false;

    setTimeout(function () {
      introJs().setOptions({
        steps: filtered,
        nextLabel: @json(__('tour.next')),
        prevLabel: @json(__('tour.prev')),
        doneLabel: @json(__('tour.done')),
        skipLabel: @json(__('tour.skip')),
        showStepNumbers: false,
        showBullets: false,
        showProgress: true,
        showSkipButton: true,
        exitOnOverlayClick: true,
        exitOnEsc: true,
        disableInteraction: true,
        scrollToElement: true,
        scrollPadding: 40,
        tooltipClass: 'mauzo-tour-tooltip',
        highlightClass: 'mauzo-tour-highlight',
        helperElementPadding: 6,
      }).oncomplete(function () {
        finished = true;
        postTour(completeUrl);
      }).onexit(function () {
        if (!finished) {
          postTour(skipUrl);
        }
      }).start();
    }, 700);
  });
})();
</script>
@endif
