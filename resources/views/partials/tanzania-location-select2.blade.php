@php
  $selectedDistrict = $selectedDistrict ?? old('district', '');
  $disableDistrictWhenEmpty = (bool) ($disableDistrictWhenEmpty ?? false);
  $selectRegionFirst = $selectRegionFirst ?? 'Select region first';
  $selectDistrict = $selectDistrict ?? 'Select district';
  $selectRegion = $selectRegion ?? 'Select region';
@endphp
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    var tanzaniaDistricts = @json(tanzania_districts());
    var selectedDistrict = @json($selectedDistrict);
    var disableDistrictWhenEmpty = @json($disableDistrictWhenEmpty);

    var selectRegionFirst = @json($selectRegionFirst);
    var selectDistrict = @json($selectDistrict);
    var selectRegion = @json($selectRegion);

    function populateDistricts(region, district) {
        var $district = jQuery('#businessDistrict');
        $district.empty();

        if (!region) {
            $district.append(new Option(selectRegionFirst, '', true, false));
            if (disableDistrictWhenEmpty) {
                $district.prop('disabled', true);
            }
        } else {
            $district.prop('disabled', false);
            $district.append(new Option(selectDistrict, '', !district, !district));
            (tanzaniaDistricts[region] || []).forEach(function (name) {
                $district.append(new Option(name, name, false, name === district));
            });
        }

        $district.val(district || '').trigger('change.select2');
    }

    window.populateTanzaniaDistricts = populateDistricts;

    jQuery(function ($) {
        var $region = $('#businessRegion');
        var $district = $('#businessDistrict');

        $region.select2({ width: '100%', placeholder: selectRegion });
        $district.select2({ width: '100%', placeholder: selectDistrict });

        $region.on('change', function () {
            populateDistricts(this.value, '');
        });

        populateDistricts($region.val(), selectedDistrict);
    });
})();
</script>
