<section id="newest-additions">

  <h2><?php echo __('Newest additions'); ?></h2>
  <ul>
  <?php foreach ($newestAdditions as $item) { ?>
    <?php $object = QubitObject::getById($item); ?>
      <li>
        <a href="<?php echo url_for(array($object)); ?>">
          <?php echo render_title($object); ?>
        </a>
      </li>
    <?php } ?>
  </ul>

</section>
