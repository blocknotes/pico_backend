<?php
/**
 * Backend plugin for Pico CMS
 *
 * @author Mattia Roccoberton
 * @link http://blocknot.es
 * @license http://opensource.org/licenses/MIT
 * @version 0.2.2
 */

final class Pico_Edit extends AbstractPicoPlugin {

  protected $enabled = true;
  protected $dependsOn = array();

  private $is_admin = false;
  private $is_logout = false;
  private $plugin_path = '';
  private $password = '';
  private $url = 'pico_edit';

  public function onPageRendering(Twig_Environment &$twig, array &$twig_vars, &$templateName)
  {
    $twig_vars['pico_edit_url'] = $this->getPageUrl( $this->url );
    if( $this->is_logout ) {
      session_destroy();
      header( 'Location: '. $twig_vars['pico_edit_url'] );
      exit;
    }

    if( $this->is_admin ) {
      $twig_vars['pico_404_url'] = $this->getPageUrl( '404' );
      // $twig_vars['pico_base_url'] = $this->getPageUrl( '/' );
      header( $_SERVER['SERVER_PROTOCOL'] . ' 200 OK' ); // Override 404 header
      $loader = new Twig_Loader_Filesystem( $this->plugin_path );
      $twig_editor = new Twig_Environment( $loader, $twig_vars );
      // $twig_vars['autoescape'] = false;
      $twig_editor->addFilter('var_dump', new Twig_Filter_Function('var_dump'));
      if( !$this->password ) {
        $twig_vars['login_error'] = 'No password set for the backend.';
        echo $twig_editor->render( 'login.html', $twig_vars ); // Render login.html
        exit;
      }

      if( !isset($_SESSION['backend_logged_in'] ) || !$_SESSION['backend_logged_in'] ) {
        if( isset($_POST['password'] ) ) {
          if( strtoupper( hash('sha256', $_POST['password'])) == $this->password ) {
            $_SESSION['backend_logged_in'] = true;
            $_SESSION['backend_config'] = $twig_vars['config'];
          }
          else {
            $twig_vars['login_error'] = 'Invalid password.';
            echo $twig_editor->render('login.html', $twig_vars); // Render login.html
            exit;
          }
        } else {
          echo $twig_editor->render('login.html', $twig_vars); // Render login.html
          exit;
        }
      }

      echo $twig_editor->render('editor.html', $twig_vars); // Render editor.html
      exit; // Don't continue to render template
    }
  }

  public function onConfigLoaded( array &$config ) {
    // Default options
    if( !isset( $config['pico_edit_404'] ) ) $config['pico_edit_404'] = TRUE;
    if( !isset( $config['pico_edit_options'] ) ) $config['pico_edit_options'] = TRUE;
    // Parse extra options
    $conf = $this->getConfigDir() . '/options.conf';
    if( !file_exists( $conf ) ) touch( $conf );
    $data = filter_var( file_get_contents( $conf ), FILTER_SANITIZE_STRING );
    foreach( preg_split( "/((\r?\n)|(\r\n?))/", $data ) as $line ) {
      $line_ = trim( $line );
      if( !empty( $line_ ) )
      {
        $pos = strpos( $line_, '=' );
        if( $pos > 0 )
        {
          $key = trim( substr( $line, 0, $pos ) );
          if( $key && $key[0] != '#' )
          {
            $value = trim( substr( $line, $pos + 1 ) );
            // if( isset( $config[$key] ) )
            $config[$key] = $value;
          }
        }
        else
        {
          $pos = strpos( $line_, '!' );
          if( $pos > 0 )
          {
            $key = trim( substr( $line, 0, $pos ) );
            if( $key && $key[0] != '#' )
            {
              $value = trim( substr( $line, $pos + 1 ) );
              // if( isset( $config[$key] ) )
              $config[$key] = ( $value == '1' || $value == 'true' || $value == 'TRUE' || $value == 'yes' || $value == 'YES' || $value == 'on' || $value == 'ON' );
            }
          }
        }
      }
    }

    $this->plugin_path = dirname( __FILE__ );
    if( file_exists( $this->plugin_path .'/config.php' ) ) {
      global $backend_password;
      global $backend_url;
      include_once( $this->plugin_path .'/config.php' );
      $this->password = $backend_password;
      if (isset($backend_url)) {
        $this->url = $backend_url;
      }
    }
    $page_404 = $this->getConfig('content_dir') . '/404.md';
    if( !file_exists( $page_404 ) ) touch( $page_404 );
  }

  // public function on404ContentLoading( &$file ) { var_dump( $file ); }
  // public function onRequestFile( &$file ) { var_dump( $file ); }

  public function onRequestUrl( &$url ) {
    // If the request is anything to do with pico_edit, then we start the PHP session
    if( substr( $url, 0, strlen($this->url) ) == $this->url ) {
      if(function_exists('session_status')) {
        if (session_status() == PHP_SESSION_NONE) {
          session_start();
        }
      } else {
        session_start();
      }
    }
    // Are we looking for /pico_edit?
    if( $url == $this->url ) $this->is_admin = true;
    if( $url == $this->url.'/new' ) $this->do_new();
    if( $url == $this->url.'/open' ) $this->do_open();
    if( $url == $this->url.'/save' ) $this->do_save();
    if( $url == $this->url.'/delete' ) $this->do_delete();
    if( $url == $this->url.'/logout' ) $this->is_logout = true;
    if( $url == $this->url.'/commit' ) $this->do_commit();
    if( $url == $this->url.'/git' ) $this->do_git();
    if( $url == $this->url.'/pushpull' ) $this->do_pushpull();
    if( $url == $this->url.'/clearcache' ) $this->do_clearcache();
  }

  /**
   * Returns real file name to be edited.
   *
   * @param string $file_url the file URL to be edited
   * @return string
   */
  private function get_real_filename( $file_url ) {
    $path = $this->getConfig( 'content_dir' ) . $file_url . $this->getConfig( 'content_ext' );
    return realpath( $path );
  }

  private function do_new()
  {
    if( !isset( $_SESSION['backend_logged_in'] ) || !$_SESSION['backend_logged_in'] ) die( json_encode( array( 'error' => 'Error: Unathorized' ) ) );
    $title = ( isset( $_POST['title'] ) && !empty( $_POST['title'] ) ) ? filter_var( trim( $_POST['title'] ), FILTER_SANITIZE_STRING ) : '';
    if( empty( $title ) ) die( json_encode( array( 'error' => 'Error: Invalid title' ) ) );

    $dir = FALSE;
    $pos = strrpos( $title, '/' );
    if( $pos === FALSE ) $name = $title;
    else $name = substr( $title, $pos + 1 );
    if( $pos > 0 )
    {
      $dir = $this->slugify( substr( $title, 0, $pos ) );
      if( empty( $dir ) ) die( json_encode( array( 'error' => 'Error: Invalid folder' ) ) );
    }
    $file = $this->slugify( $name );
    if( empty( $file ) ) die( json_encode( array( 'error' => 'Error: Invalid page name' ) ) );

    $path = $this->getConfig( 'content_dir' );
    if( !empty( $dir ) )
    {
      $path .= $dir;
      if( !is_dir( $path ) )
      {
        if( !mkdir( $path ) ) die( json_encode( array( 'error' => 'Can\'t create folder' ) ) );
      }
    }
    $path .= '/' . $file . $this->getConfig( 'content_ext' );

    // TODO: check this part

    // // From the bottom of the $contentDir, look for format templates,
    // // working upwards until we get to CONTENT_DIR
    // $template = null;
    // $workDir = $contentDir;
    // while(strlen($workDir) >= strlen(CONTENT_DIR)) {
    //   // See if there's a format template here...?
    //   if(file_exists($workDir . 'format.templ')) {
    //     $template = strip_tags(substr($workDir . 'format.templ', strlen(CONTENT_DIR)));
    //     break;
    //   }
    //   // Now strip off the last bit of path from the $workDir
    //   $workDir = preg_replace('/[^\/]*\/$/', '', $workDir);
    // }

    // $file .= CONTENT_EXT;

    // $content = null;
    // if(!is_null($template)) {
    //   $loader = new Twig_Loader_Filesystem(CONTENT_DIR);
    //   $twig = new Twig_Environment($loader, array('cache' => null));
    //   $twig->addExtension(new Twig_Extension_Debug());
    //   $twig_vars = array(
    //     'title' => $title,
    //     'date' => date('j F Y'),
    //     'time' => date('h:m:s'),
    //     'author' => 'ralph',
    //   );
    //   $content = $twig->render($template, $twig_vars);
    // }

    $error = '';
    // if( is_null( $content ) ) {
    $content = "/*\nTitle: $name\nAuthor: " . ( $this->getConfig( 'pico_edit_default_author' ) ? $this->getConfig( 'pico_edit_default_author' ) : '' ) . "\nDate: ". date('j F Y') . "\n*/\n\n";
    // }

    if( file_exists( $path ) ) $error = 'Error: A post already exists with this title';
    else
    {
      if( strlen( $content ) !== file_put_contents( $path, $content ) ) $error = 'Error: can not create the post ... ';
    }

    $f = ( !empty( $dir ) ? ( $dir . '/' ) : '' ) . $file;
    die( json_encode( array(
      'title' => $title,
      'content' => $content,
      'file' => $f,
      'url' => $this->getPageUrl( $f ),
      'error' => $error
    ) ) );
  }

  private function do_open() {
    if( !isset( $_SESSION['backend_logged_in'] ) || !$_SESSION['backend_logged_in'] ) die( json_encode( array( 'error' => 'Error: Unathorized' ) ) );
    $file_url = isset( $_POST['file'] ) && $_POST['file'] ? $_POST['file'] : '';
    if( $file_url != 'conf' )
    {
      $file = $this->get_real_filename( $file_url );
      if( $file && file_exists( $file ) ) die( file_get_contents( $file ) );
      else die( 'Error: Invalid file' );
    }
    else if( $this->getConfig( 'pico_edit_options' ) )
    {
      $conf = $this->getConfigDir() . '/options.conf';
      if( file_exists( $conf ) ) die( file_get_contents( $conf ) );
      else die( 'Error: Invalid options file' );
    }
  }

  private function do_save() {
    if( !isset( $_SESSION['backend_logged_in'] ) || !$_SESSION['backend_logged_in'] ) die( json_encode( array( 'error' => 'Error: Unathorized' ) ) );
    $file_url = isset( $_POST['file'] ) && $_POST['file'] ? $_POST['file'] : '';
    if( $file_url != 'conf' )
    {
      $file = $this->get_real_filename( $file_url );
      if( !$file ) die( 'Error: Invalid file' );
      $content = isset( $_POST['content'] ) && $_POST['content'] ? $_POST['content'] : '';
      if( !$content ) die( 'Error: Invalid content' );
      $error = '';
      if( strlen( $content ) !== file_put_contents( $file, $content ) ) $error = 'Error: cant save changes';
      die( json_encode( array(
        'content' => $content,
        'file' => $file_url,
        'error' => $error
      )));
    }
    else if( $this->getConfig( 'pico_edit_options' ) )
    {
      $conf = $this->getConfigDir() . '/options.conf';
      $content = ( isset( $_POST['content'] ) && $_POST['content'] ) ? filter_var( $_POST['content'], FILTER_SANITIZE_STRING ) : '';
      $error = '';
      if( strlen( $content ) !== file_put_contents( $conf, $content ) ) $error = 'Error: cant save changes';
      die( json_encode( array( 'content' => $content, 'file' => $conf, 'error' => $error ) ) );
    }
  }

  private function do_delete() {
    if( !isset( $_SESSION['backend_logged_in'] ) || !$_SESSION['backend_logged_in'] ) die( json_encode( array( 'error' => 'Error: Unathorized' ) ) );
    $file_url = isset( $_POST['file'] ) && $_POST['file'] ? $_POST['file'] : '';
    $file = $this->get_real_filename( $file_url );
    if( !$file ) die( 'Error: Invalid file' );
    if( file_exists( $file ) ) {
      $ret = unlink( $file );
      // if sub dir and its empty: remove it
      $dir = dirname( $file );
      if( $dir && $dir != '/' )
      {
        if( count( glob( $dir . '/*' ) ) === 0 )
        {
          rmdir( $dir );
        }
      }
      die( $ret );
    }
  }

  private function do_commit()
  {
    if(!isset($_SESSION['backend_logged_in']) || !$_SESSION['backend_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
      return $this->do_commit_post();
    }
    return $this->do_commit_get();
  }

  private function do_commit_get()
  {
    if(file_exists('./plugins/pico_edit/commitform.html')) {
      # Do the git stuff...
      require_once 'Git-php-lib';
      $repo = Git::open('.');
      $status = '';
      try {
        $status = $repo->porcelain();
      }
      catch(Exception $e) {
        $status = array('Failed to run git-status: ' . $e->getMessage());
      }

      $loader = new Twig_Loader_Filesystem('./plugins/pico_edit');
      $twig = new Twig_Environment($loader, array('cache' => null));
      $twig->addExtension(new Twig_Extension_Debug());
      $twig_vars = array(
        'status' => $status,
      );
      $content = $twig->render('commitform.html', $twig_vars);
      die($content);
    } else {
      die('Sorry, commitform.html was not found in the backend plugin. This is an installation problem.');
    }
  }

  private function do_commit_post()
  {
    // $_REQUEST['file'] is an array of file names. We don't trust our client,
    // so will re-run 'porcelain' to get a list of files. We'll only 'git add'
    // any files supplied by the user that are in the list we get from porcelain
    // we'll then go ahead and commit them with the message supplied
    require_once 'Git-php-lib';
    $repo = Git::open('.');
    $status = $repo->porcelain();
    $git_files = array();
    foreach($status as $item) {
      $git_files[$item['file']] = $item['y'];
    }

    $to_add = array();
    $to_rm = array();
    foreach($_REQUEST['file'] as $requested_file) {
      if(array_key_exists($requested_file, $git_files)) {
        if($git_files[$requested_file] == 'D') {
          $to_rm[] = $requested_file;
        } else {
          $to_add[] = $requested_file;
        }
      }
    }

    $add_output = '';
    if(count($to_add) > 0) {
      try {
        $add_output = $repo->add($to_add);
      }
      catch(Exception $e) {
        $add_output = 'Failed to run git-add: ' . $e->getMessage();
      }
    }
    #$add_output = preg_replace('/\r?\n\r?/', "<br>\n", $add_output);
    if(count($to_rm) > 0) {
      $rm_output = '';
      try {
        $rm_output = $repo->rm($to_rm);
      }
      catch(Exception $e) {
        $rm_output = 'Failed to run git-rm: ' . $e->getMessage();
      }
    }

    $commit_output = '';
    try {
      $commit_output = $repo->commit($_REQUEST['message'], false);
    }
    catch(Exception $e) {
      $commit_output = 'Failed to run git-commit: ' . $e->getMessage();
    }
    #$commit_output = preg_replace('/\r?\n\r?/', "<br>\n", $add_output);

    if(file_exists('./plugins/pico_edit/commitresponse.html')) {
      $loader = new Twig_Loader_Filesystem('./plugins/pico_edit');
      $twig = new Twig_Environment($loader, array('cache' => null));
      $twig->addExtension(new Twig_Extension_Debug());
      $twig_vars = array(
        'add' => $add_output,
        'rm' => $rm_output,
        'commit' => $commit_output,
      );
      $content = $twig->render('commitresponse.html', $twig_vars);
      die($content);
    } else {
      die('Sorry, commitresponse.html was not found in the backend plugin. This is an installation problem.');
    }
  }

  private function do_pushpull()
  {
    if(!isset($_SESSION['backend_logged_in']) || !$_SESSION['backend_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
      return $this->do_pushpull_post();
    }
    return $this->do_pushpull_get();
  }

  private function do_pushpull_get()
  {
    if(file_exists('./plugins/pico_edit/pushpullform.html')) {
      # Do the git stuff...
      require_once 'Git-php-lib';
      $repo = Git::open('.');
      $remotes = '';
      try {
        $remotes_string = $repo->run('remote');
        $remotes = preg_split('/\s*\r?\n\r?\s*/', $remotes_string, 0, PREG_SPLIT_NO_EMPTY);
      }
      catch(Exception $e) {
        $remotes = array('Failed to get git sources: ' . $e->getMessage());
      }

      $loader = new Twig_Loader_Filesystem('./plugins/pico_edit');
      $twig = new Twig_Environment($loader, array('cache' => null));
      $twig->addExtension(new Twig_Extension_Debug());
      $twig_vars = array(
        'remotes' => $remotes,
      );
      $content = $twig->render('pushpullform.html', $twig_vars);
      die($content);
    } else {
      die('Sorry, pushpullform.html was not found in the backend plugin. This is an installation problem.');
    }
  }

  private function do_pushpull_post()
  {
    if(file_exists('./plugins/pico_edit/pushpullresponse.html')) {
      # Do the git stuff...
      require_once 'Git-php-lib';
      $repo = Git::open('.');
      $remotes = array();
      try {
        $remotes_string = $repo->run('remote');
        $remotes = preg_split('/\s*\r?\n\r?\s*/', $remotes_string, 0, PREG_SPLIT_NO_EMPTY);
      }
      catch(Exception $e) {
        $status = array('Failed to get git sources: ' . $e->getMessage());
      }

      $output = 'xyz';

      # Now make the the selected repo is one in the remotes list
      if(in_array($_REQUEST['remote'], $remotes)) {
        # Selected repo is acceptable, so go Git push/pull

        try {
          if($_REQUEST['operation'] == 'push') {
            $output = $repo->push($_REQUEST['remote'], 'master');
            error_log("output = $output");
          } elseif($_REQUEST['operation'] == 'pull') {
            $output = $repo->pull($_REQUEST['remote'], 'master');
          } else {
            $output = 'Sorry, that operation is not allowed';
          }
        }
        catch(Exception $e) {
          $output = $e->getMessage();
        }
      } else {
        # Not an acceptable remote
        $output = 'Sorry, that remote is not allowed';
      }

      # And do output...
      $loader = new Twig_Loader_Filesystem('./plugins/pico_edit');
      $twig = new Twig_Environment($loader, array('cache' => null));
      $twig->addExtension(new Twig_Extension_Debug());
      $twig_vars = array(
        'output' => $output,
      );
      $content = $twig->render('pushpullresponse.html', $twig_vars);
      die($content);
    } else {
      die('Sorry, pushpullresponse.html was not found in the backend plugin. This is an installation problem.');
    }
  }

  private function do_git()
  {
    if(!isset($_SESSION['backend_logged_in']) || !$_SESSION['backend_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));

    $output = array(
      'have_git' => 0,
      'have_repo' => 0,
      'remotes' => array(),
    );

    require_once 'Git-php-lib';
    $output['have_git'] = GitRepo::test_git();

    if($output['have_git']) {
      try {
        $repo = Git::open('.');
        if(Git::is_repo($repo)) {
          $output['have_repo'] = true;

          $remotes_string = $repo->run('remote');
          $output['remotes'] = preg_split('/\s*\r?\n\r?\s*/', $remotes_string, 0, PREG_SPLIT_NO_EMPTY);
        }
      }
      catch(Exception $e) { }
    }

    die(json_encode($output));
  }

  private function do_clearcache()
  {
    if(!isset($_SESSION['backend_logged_in']) || !$_SESSION['backend_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
    $path = realpath( $this->getConfig( 'content_dir' ) . '/cache' );
    if( $path !== FALSE )
    {
      $path .= '/*';
      $ret = `rm -rf $path`;  // TODO: improve me using unlink
    }
    else $ret = 0;
    die($ret);
  }

  private function slugify( $text ) {
    // replace non letter or digits by -
    $text = preg_replace( '~[^\\pL\d]+~u', '-', $text );
    // trim
    $text = trim( $text, '-' );
    // transliterate
    $text = iconv( 'utf-8', 'us-ascii//TRANSLIT', $text );
    // lowercase
    $text = strtolower( $text );
    // remove unwanted characters
    $text = preg_replace( '~[^-\w]+~', '', $text );

    return !empty( $text ) ? $text : FALSE;
  }
}

// This is for Vim users - please don't delete it
// vim: set filetype=php expandtab tabstop=2 shiftwidth=2:
