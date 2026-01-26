<?php
require_once __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>

<html>
<head>

</head>
<body class="p-5">
  <select id="direct_superior" class="form-select" style="width: 300px;">
    <option value="">Select supervisor...</option>
    <option>John Doe</option>
    <option>Jane Smith</option>
    <option>Ali Ahmad</option>
    <option>Maria Chen</option>
  </select>

  <script>
  $(document).ready(function() {
      $('#direct_superior').select2({
          theme: 'bootstrap-5',
          width: '100%',
          minimumResultsForSearch: 0
      });
  });
  </script>
</body>
</html>
