UPDATE wp_options SET option_value = replace(option_value, '{{ old_domain }}', '{{ domain }}') WHERE option_name = 'home' OR option_name = 'siteurl';

UPDATE wp_posts SET guid = replace(guid, '{{ old_domain }}','{{ domain }}');

UPDATE wp_posts SET post_content = replace(post_content, '{{ old_domain }}', '{{ domain }}');

UPDATE wp_postmeta SET meta_value = replace(meta_value,'{{ old_domain }}','{{ domain }}');