<?php

/**

 * Tự đánh dấu vào nút Remember Me để ghi nhớ lần đăng nhập sau

 */

// add_action('woocommerce_after_customer_login_form', 'add_text_after_login');



function smallenvelop_login_message( $message ) {

  if ( empty($message) ){

      return '

      <h4 class="Connect-title-2786">Đăng nhập</h4>

      <section class="loginToIsocal">

        <div class="Connect-btnArea-5197">

          <div class="loginToFacebook button-login-isocal">

            <a class="btn buttonFacebook" href="javascript:void(0)">

              <svg aria-hidden="true" class="svg-inline--fa fa-facebook-f fa-w-10" data-icon="facebook-f" data-prefix="fab" focusable="false" role="img" style="height: 14px; position: relative; top: -2px;" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg">

                <path class="" d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z" fill="currentColor"></path>

              </svg> Facebook

            </a>

          </div>

          <div class="loginToEmail button-login-isocal">

            <a class="btn buttonGoogle" href="javascript:void(0)">

              <svg aria-hidden="true" class="svg-inline--fa fa-google-plus-g fa-w-20" data-icon="google-plus-g" data-prefix="fab" focusable="false" role="img" style="height: 14px; position: relative; top: -2px;" viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg">

                <path class="" d="M386.061 228.496c1.834 9.692 3.143 19.384 3.143 31.956C389.204 370.205 315.599 448 204.8 448c-106.084 0-192-85.915-192-192s85.916-192 192-192c51.864 0 95.083 18.859 128.611 50.292l-52.126 50.03c-14.145-13.621-39.028-29.599-76.485-29.599-65.484 0-118.92 54.221-118.92 121.277 0 67.056 53.436 121.277 118.92 121.277 75.961 0 104.513-54.745 108.965-82.773H204.8v-66.009h181.261zm185.406 6.437V179.2h-56.001v55.733h-55.733v56.001h55.733v55.733h56.001v-55.733H627.2v-56.001h-55.733z" fill="currentColor"></path>

              </svg> Google

            </a>

          </div>

        </div>

        <hr class="Content-content-180" data-content="Đăng nhập với tài khoản website">

      </section>

      ';

  } else {

      return $message;

  }

}

add_filter( 'login_message', 'smallenvelop_login_message' );



//Change footer

function remove_footer_admin()

{

  echo 'Production Mijuri <a href="https://mijuri.co" target="_blank">Theme</a> | System by <a href="#" target="_blank">Mijuri Developer</a></p>';

}

add_filter('admin_footer_text', 'remove_footer_admin');



// Sửa lại title trang đăng nhập

function my_admin_title($admin_title, $title)

{

  return get_bloginfo('name') . ' &bull; ' . $title;

}

add_filter('admin_title', 'my_admin_title', 10, 2);



function custom_login_title($origtitle)

{

  return get_bloginfo('name') . ' - Đăng Nhập';

}

add_filter('login_title', 'custom_login_title', 99);



// change url of login logo link

add_filter('login_headerurl', 'custom_loginlogo_url');



function custom_loginlogo_url($url)

{



  return "https://stywin.com";

}