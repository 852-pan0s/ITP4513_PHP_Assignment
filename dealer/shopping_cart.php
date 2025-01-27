<!DOCTYPE html>
<html>

<head>
    <title>New Order</title>
    <script src="./node_modules/jquery/dist/jquery.min.js"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

    <link rel="stylesheet" href="./css/mdc_typography.css">

    <!--top / menu bar lib-->
    <link rel="stylesheet" href="./css/index.css">
    <!--  <script src="./js/index.js"></script>-->

    <!--mdc optional-->
    <!--
    <link rel="stylesheet" href="./css/shopping.css">
    <script src="./js/shopping.js"></script>
  -->
    <link rel="stylesheet" href="./css/mdc_checkbox.css">
    <!--  <script src="./js/mdc_checkbox.js"></script>-->

    <!--  semantic-ui library-->
    <link rel="stylesheet" type="text/css" href="./node_modules/semantic-ui/dist/semantic.min.css">
    <script src="./node_modules/semantic-ui/dist/semantic.min.js"></script>

    <!-- material-design life lib-->
    <link rel="stylesheet" href="./node_modules/material-design-lite/material.min.css">
    <script src="./node_modules/material-design-lite/material.min.js"></script>

    <!--  my css or js-->
    <link rel="stylesheet" href="./css/mycss.css">
    <script src="./js/myjs.js"></script>

    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <script src="./js/bootstrap.min.js"></script>
</head>
<body class="mdc-typography"
'>

<?php
session_start();
//Database connection part
$hostname = "127.0.0.1";
$database = "projectDB";
$username = "root";
$password = "";
$conn = mysqli_connect($hostname, $username, $password, $database);
$neworder = false;
$noEffect = "";
$page = 1;
$search = "";
if (!isset($_SESSION["dealerID"])) {//if the dealer does not log in before
  header("location:login.php");//redirect to login page
} else {
  $sql = "SELECT * FROM dealer WHERE dealerID = '{$_SESSION['dealerID']}'";
  $rs = mysqli_query($conn, $sql); // Get dealer information
  $rc = mysqli_fetch_assoc($rs); // Take the first row
  extract($rc);
}

if (isset($_GET["ok"])) {
  echo "<script> window.location.assign('history.php?new={$_GET['ok']}');</script>";
}

if (isset($_GET["page"])) {//if the dealer click the page button
  $page = $_GET["page"];
  echo "<script>
$(document).ready(function(){
    $('#btn_showList').click() //the Add Part button is clicked by the system
});
</script>";
}

if (isset($_GET["q"])) {//if the dealer click the search button
  $search = $_GET["q"];
  echo "<script>
$(document).ready(function(){
    $('#btn_showList').click()//the Add Part button is clicked by the system
});
</script>";
}


if (isset($_GET["neworder"])) {//if the dealer create a new shopping cart (new order)
  if ($_GET["neworder"]) {
    $_SESSION[$dealerID]["shopping_cart"] = true; //give a shopping cart to the dealer
    header("location:shopping_cart.php"); //redirect to the shopping_cart page
  }
}
if (isset($_SESSION[$dealerID]["shopping_cart"])) {//if(the dealer has a shopping cart
  if ($_SESSION[$dealerID]["shopping_cart"] == true) {//if(the dealer has a shopping cart
    echo "<script>showShoppingCart();</script>"; //set the max height of shopping_cart div and hide the New button
  }
}
if (isset($_GET["add"])) { //if the dealer add  parts
  $partList = explode(",", $_GET["add"]); //convert string to array by ','
  foreach ($partList as $key => $value) {
    $_SESSION["shopping_cart"][$value]["partNumber"] = "$value";//add part to the shopping cart
    header("location:shopping_cart.php");//redirect to the shopping_cart page
  }
}
if (isset($_GET["delete"])) {//delete part
  delete($_GET["delete"]);
  header("location:shopping_cart.php");//redirect to the shopping_cart page
}

if (isset($_GET["address"])) {// place order
  $insufficientStock = false; //used for check the stock is enough
  $insufficientPart = "";
  $unavailableStatus = false; //used for check the stock is available or not
  $unavailablePart = "";
  $skip = 0;//skip the first element of $_GET (delivery address)
  //check the part stock quantity is enought for the dealer to place an order
  foreach ($_GET as $part => $quantity) {//$_GET["partNumber"] = $quantity
    if ($skip++ == 0) continue; //skip the first element (delivery address)
    $sql = "SELECT * FROM part WHERE partNumber = $part";
    $rs = mysqli_query($conn, $sql);
    $rc = mysqli_fetch_assoc($rs);
    if ($rc["stockQuantity"] < $quantity) {
      $insufficientStock = true;
      $insufficientPart .= "{$rc['partName']}, "; //record the part which the stock is insufficient
    }
    if ($rc["stockStatus"] != 1) { //if the part is not available
      $unavailableStatus = true;
      $unavailablePart .= "{$rc['partName']}, "; //record the part which the status is unavailable
    }
  }
  if ($unavailableStatus) {
    $unavailablePart = substr($unavailablePart, 0, strlen($unavailablePart) - 2);//delete last ', ';
  }
  if ($insufficientStock) {
    $insufficientPart = substr($insufficientPart, 0, strlen($insufficientPart) - 2);//delete last ', '
  }
  if ($unavailableStatus && $insufficientStock) {
    header("location:{$_SERVER['PHP_SELF']}?insufficient=$insufficientPart&unavailablePart=$unavailablePart");
  } else if ($unavailableStatus) {
    header("location:{$_SERVER['PHP_SELF']}?unavailablePart=$unavailablePart");
  } else if ($insufficientStock) {
    header("location:{$_SERVER['PHP_SELF']}?insufficient=$insufficientPart");
  } else { //if the stock is enough
    $skip = 0; //skip the first element of $_GET (delivery address)
    $delete = ""; //used for delete the part from the shopping cart
    $deliveryAddress = $_GET["address"];
    $today = date("Y-m-d"); //today
    $sql = "INSERT INTO orders VALUES(null,'$dealerID','$today','$deliveryAddress',1)";
    mysqli_query($conn, $sql); //Create a new order
    foreach ($_GET as $part => $quantity) {//$_GET["partNumber"] = $quantity
      if ($skip++ == 0) continue; //skip the first element (delivery address)
      // echo "$part = $quantity<br>";
      $delete .= $part . ",";
      $_SESSION["placeOrder"][$part] = "$quantity";//set the part and the order quantity
      $sql = "SELECT * FROM orders WHERE dealerID = '$dealerID' ORDER BY orderID DESC"; //get the latest order id
      $rs = mysqli_query($conn, $sql);
      $rc = mysqli_fetch_assoc($rs);
      extract($rc);
    }
    //insert the part and quantity to orderpart
    foreach ($_SESSION["placeOrder"] as $part => $quantity) {
      $sql = "SELECT * FROM part WHERE partNumber = $part";
      $rs = mysqli_query($conn, $sql);
      $rc = mysqli_fetch_assoc($rs);
      extract($rc);
      $sql = "UPDATE part set stockQuantity = stockQuantity-$quantity WHERE partNumber = $part";
      mysqli_query($conn, $sql);
      $sql = "INSERT INTO orderpart VALUES($orderID, $part, $quantity,$stockPrice)";
      mysqli_query($conn, $sql);
    }

    unset($_SESSION["placeOrder"]);
    $deletePart = substr($delete, 0, strlen($delete) - 1); //remove last ','
    //echo($deletePart);
    delete($deletePart);
    header("location:shopping_cart.php?ok=$orderID");
  }
}

function delete($delete)
{
  $partList = explode(",", $delete); //convert string to array by ','
  foreach ($partList as $key => $value) {
//      echo "$value";
    unset($_SESSION["shopping_cart"][$value]);//delete the part from the shopping cart
  }
}

?>


<script>
    function checkAddress() {
        if ($('#address').parent().hasClass("is-invalid")) {
            $('#invalidAddress').removeClass('hide');
            return false;

        } else {
            $('#invalidAddress').addClass('hide');
            return true;
        }
    }

    $(document).ready(function () {
        $('#btn_buy').on('click', function () {
            if ($('#address').parent().hasClass("is-invalid")) {
                $('#invalidAddress').removeClass('hide');
                return;
            } else {
                $('#invalidAddress').addClass('hide');
                var $get = "?address=" + $("#address").val();
                var shopping_partlist = document.getElementById('partList').children; //tbody>tr
                for (i = 0; i < shopping_partlist.length; i++) {
                    if (shopping_partlist[i].classList.contains('is-selected')) {//tbody>tr[i]
                        var partNumber = shopping_partlist[i].children[1].textContent;//tbody>tr[i]>td(id)
                        var quantity = shopping_partlist[i].children[3].children[0].value;//tbody>tr[i]>td>input(quantity)
                        $get += `&${partNumber}=${quantity}`;//set part number and the quantity that the dealer wants to place
                    }
                }
                //console.log($get);
                window.location.assign(`<?php echo $_SERVER["PHP_SELF"] ?>${$get}`); //add part to the shopping cart
            }
        });

        $('#btn_delete').on('click', function () {
            var $get = "?delete=";
            var shopping_partlist = document.getElementById('partList').children; //tbody>tr
            for (i = 0; i < shopping_partlist.length; i++) {
                if (shopping_partlist[i].classList.contains('is-selected')) {//tbody>tr[i]
                    var partNumber = shopping_partlist[i].children[1].textContent;//tbody>tr[i]>td
                    $get += `${partNumber},`;//add part number for delete from the shopping cart
                }
            }
            $get = $get.substring(0, $get.length - 1); //remove last ','
            //console.log($get);
            window.location.assign(`<?php echo $_SERVER["PHP_SELF"] ?>${$get}`); //delete the part from the shopping cart
        });


        $('#btn_add').on('click', function () {
            var $get = "?add=";
            var shopping_partlist = document.getElementById('selectpart').children; //tbody>tr
            for (i = 0; i < shopping_partlist.length; i++) {
                if (shopping_partlist[i].children[0].children[0].children[0].checked) {//tbody>tr[i]>td>label>input first column (checkbox)
                    var partNumber = shopping_partlist[i].children[1].textContent;//tbody>tr[i]>td
                    $get += `${partNumber},`;//add partnumber which the dealer wants to add to the shopping cart
                }
            }
            $get = $get.substring(0, $get.length - 1); //remove last ','
            window.location.assign(`<?php echo $_SERVER["PHP_SELF"] ?>${$get}`); //add part to the shopping cart
        });

        $('#btn_search').on('click', function () {
            var search = $('#search').val(); //set the keyword
            window.location.assign(`<?php echo $_SERVER["PHP_SELF"] ?>?q=${search}`); //search part
        })
    });
</script>

<header class="mdc-top-app-bar app-bar" id="app-bar">
    <div class="mdc-top-app-bar__row">
        <section class="mdc-top-app-bar__section mdc-top-app-bar__section--align-start">
            <button class="material-icons mdc-top-app-bar__navigation-icon">menu</button>
            <span class="mdc-top-app-bar__title">SMLCs Order System</span>
        </section>
        <section class="mdc-top-app-bar__section mdc-top-app-bar__section--align-end" role="toolbar">
            <button id="demo-menu-lower-right" class="material-icons mdc-top-app-bar__action-item"
                    style="padding: 6px;font-size: 36px;">account_circle
            </button>
            <ul class="mdl-menu mdl-menu--bottom-right mdl-js-menu mdl-js-ripple-effect" for="demo-menu-lower-right">
              <?php if (!isset($_SESSION["dealerID"])) { ?>
                  <li class="mdl-menu__item" onclick="window.location.assign('login.php')">Log in</li>
                  <li class="mdl-menu__item" onclick="window.location.assign('register.php');">Sign up</li>
              <?php } else { ?>
                  <li class="mdl-menu__item"
                  <li class="mdl-menu__item" onclick="window.location.assign('login.php?logout=true'); ">Log out</li>
                  </li>
                  <li class="mdl-menu__item" onclick="window.location.assign('profile_editing.php');">Profile</li>
              <?php } ?>
            </ul>
        </section>
    </div>
</header>
<aside class="mdc-drawer mdc-drawer--dismissible" id="mdc-drawer">
    <div class="mdc-drawer__content">
        <nav class="mdc-list">
            <a class="mdc-list-item" href="history.php">
                <i class="material-icons mdc-list-item__graphic" aria-hidden="true">history</i>
                <span class="mdc-list-item__text">Order History</span>
            </a>
            <a class="mdc-list-item" href="shopping_cart.php">
                <i class="material-icons mdc-list-item__graphic" aria-hidden="true">shopping_cart</i>
                <span class="mdc-list-item__text">Make Order</span>
            </a>
        </nav>
    </div>
</aside>

<!--main content-->
<div class="mdc-drawer-app-content mdc-top-app-bar--fixed-adjust">
    <main class="main-content mdc_typography mdc-typography--subtitle2" id="main-content">

        <div class="form_lar">
            <h2 class="mdc-typography--headline4">New Order</h2>
            <ol class="mdc-typography--headline6">
                <li>You can place an order on this page.</li>
                <li>To place an order please follow the step that shown below.</li>
            </ol>
        </div>
        <div class="form_lar">
            <h2 class="mdc-typography--headline5">Step</h2>
            <div class="my--mainContent-header">
                <!--status: step active disabled-->
                <div class="ui steps">
                    <div class="active step">
                        <i class="cart arrow down icon"></i>
                        <div class="content">
                            <div class="title">Place Order</div>
                            <div class="description">Add parts and place your order.</div>
                        </div>
                    </div>
                    <div class="disabled step">
                        <i class="truck icon"></i>
                        <div class="content">
                            <div class="title">Delivery</div>
                            <div class="description">Your ordered parts are delivering.</div>
                        </div>
                    </div>
                    <div class="disabled step">
                        <i class="info icon"></i>
                        <div class="content">
                            <div class="title">Confirm</div>
                            <div class="description">Receive the part.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!--New order-->
        <div class="form_lar">
            <h2 class="mdc-typography--headline5">Order</h2>
            <button id="btn_new" type="button"
                    class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent my-control-bar-button"
                    onclick="window.location.assign('shopping_cart.php?neworder=true')">New
            </button>

            <div id="shopping_cart">
                <div>
                    <!--      form method="post"-->
                    <form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="get" onsubmit="checkAddress();"
                          id="order"
                          onchange="selectedCheckBox()">
                        <!--   control bar-->
                        <div class="my-control-bar">
                            <button type="button"
                                    class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent my-control-bar-button"
                                    data-toggle="modal" data-target="#exampleModalCenter" id="btn_showList"
                                    onclick="init_btn_add();">Add
                                Parts
                            </button>


                            <button type="button" id="btn_buy"
                                    class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent my-control-bar-button"
                                    disabled="disabled">Place Order
                            </button>

                            <button type="button" id="btn_delete"
                                    class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent my-control-bar-button"
                                    disabled="disabled">Delete
                            </button>

                        </div>
                        <div class='error-msg hide' style='font-size: 20px;' id="invalidAddress">*Address is invalid.
                        </div>
                        <ul type="none" class="mdc-typography--headline6">
                            <li>
                                <!--    Delivery address-->
                                Delivery Address:
                                <div class="mdl-textfield mdl-js-textfield">
                                    <input class="mdl-textfield__input" name="deliveryAddress" type="text" id="address"
                                           title="Your delivery address" pattern="[a-zA-Z\d., ?';\[\]/\-_=]{5,255}"
                                           maxlength="255"
                                           placeholder="e.g.:flat  14/A, O house, Hello Road Street"
                                           value="<?php echo $address; ?>"
                                           readonly required style="padding: 0">
                                </div>

                                <!--        checkbox for default address google lib-->
                                <div class="mdc-form-field">
                                    <div class="mdc-checkbox">
                                        <input type="checkbox" class="mdc-checkbox__native-control"
                                               id="use_default_addr" checked
                                               onclick="isCheckedDefault('<?php echo $address; ?>');"/>
                                        <div class="mdc-checkbox__background">
                                            <svg class="mdc-checkbox__checkmark" viewBox="0 0 24 24">
                                                <path class="mdc-checkbox__checkmark-path" fill="none"
                                                      d="M1.73,12.91 8.1,19.28 22.79,4.59"/>
                                            </svg>
                                            <div class="mdc-checkbox__mixedmark"></div>
                                        </div>
                                    </div>
                                    <label for="use_default_addr">Use default address as delivery address</label>
                                </div>
                            </li>
                        </ul>

                      <?php if (isset($_GET['insufficient'])) {
                        echo "<div class='error-msg' style='font-size: 20px;'>Place order fail! The stock quantity of the following part(s) is not enouth:<br>{$_GET['insufficient']} </div>";
                      }
                      if (isset($_GET['unavailablePart'])) {
                        echo "<div class='error-msg' style='font-size: 20px;'><br>Place order fail! The status of the following part(s) is unavailable:<br>{$_GET['unavailablePart']} </div>";
                      } ?>

                        <div style="margin-top: 24px;">
                            <table class="mdl-data-table mdl-js-data-table mdl-data-table--selectable mdl-shadow--2dp">
                                <thead>
                                <tr>
                                    <th class="mdl-data-table__cell--non-numeric">#ID</th>
                                    <th class="mdl-data-table__cell--non-numeric">Part Name</th>
                                    <th>Quantity</th>
                                    <th>Stock</th>
                                    <th>Unit price</th>
                                    <th>Total price</th>
                                </tr>
                                </thead>
                                <tbody id="partList">

                                <?php

                                if (isset($_SESSION["shopping_cart"])) { //if the dealer has clicked the New button
                                  foreach ($_SESSION["shopping_cart"] as $key => $order) { //$key=partNumber, $order is the part that in the shopping cart
                                    $sql = "SELECT * FROM part WHERE partNumber = {$order['partNumber']}"; //get the information of the part
                                    $rs = mysqli_query($conn, $sql);
                                    $rc = mysqli_fetch_assoc($rs);
                                    extract($rc);
                                    $orderline = <<<HTML_CODE
   <tr class="tr_part">
         <td class="mdl-data-table__cell--non-numeric td_partNumber">$partNumber</td>
      <td class="mdl-data-table__cell--non-numeric td_partNumber">$partName</td>
      <td>
        <input type="number" id="qty{$order['partNumber']}" class="rightAlign" value="1" max="$stockQuantity" min="1"                     onchange="countAmount('#qty{$order['partNumber']}','#price{$order['partNumber']}','#total{$order['partNumber']}');" oninput="//set oninput event javascript
        if($('#qty{$order['partNumber']}').val()>$stockQuantity){ //if the order quantity is greater than stock quantity
            $('#qty{$order['partNumber']}').val($stockQuantity); //set the order quantity to stock quantity
        }else if($('#qty{$order['partNumber']}').val()<1){//if the order quantity <1
            $('#qty{$order['partNumber']}').val(1);//set the order quantity to 1
        }"
         required>
      </td>
      <td><span id="stock{$order['partNumber']}">$stockQuantity</span></td>
      <td>$<span id="price{$order['partNumber']}">$stockPrice</span></td>
      <td>$<span id="total{$order['partNumber']}">$stockPrice</span></td>
   </tr>
HTML_CODE;
                                    echo $orderline; //print the table row (order line in the shopping cart)
                                  }
                                }
                                ?>
                                </tbody>
                            </table>
                            <table class="mdl-data-table mdl-js-data-table mdl-shadow--2dp">
                                <thead>
                                <tr>
                                    <th class="mdl-data-table__cell--non-numeric">Total Amount</th>
                                    <th>$<span id="totalAmount">0</span></th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal -->
<div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalCenterTitle">Part list:</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="ui aligned basic segment mdc-typography">
                    <div class="ui left icon action input" style="margin-bottom: 24px">
                        <i class="search icon"></i>
                        <input type="text" placeholder="Part name" id="search">
                        <div class="ui blue submit button" id="btn_search">Search</div>
                    </div>
                    <table class="mdl-data-table mdl-js-data-table mdl-data-table--selectable mdl-shadow--2dp"
                           onchange="selectedCheckBoxFromDB()">
                        <thead id="tbl_part_list_head">
                        <tr>
                            <th class="mdl-data-table__cell--non-numeric text_left">#ID</th>
                            <th class="mdl-data-table__cell--non-numeric text_left full">Part Name</th>
                            <th class="mdl-data-table__cell--non-numeric text_left">Stock</th>
                            <th class="mdl-data-table__cell--non-numeric text_left">Unit price</th>
                        </tr>
                        </thead>
                        <tbody id="selectpart">

                        <?php
                        if (strlen($search) > 0) {
                          $sql = "SELECT * FROM part WHERE partName LIKE '%$search%' AND stockStatus = 1 AND stockQuantity >0"; //get the information of all part
                        } else {
                          $sql = "SELECT * FROM part WHERE stockStatus = 1 AND stockQuantity >0"; //get the information of all part
                        }
                        $rs = mysqli_query($conn, $sql);
                        $row = mysqli_fetch_all($rs); //get all the row and store to $row
                        $start = $page * 5 - 5; //every page shows 5 part
                        $end = $start + 5;
                        for ($i = $start; $i < $end && $i < count($row); $i++) {
                          $partNumber = $row[$i][0];
                          $partName = $row[$i][2];
                          $stockQuantity = $row[$i][3];
                          $stockPrice = $row[$i][4];
                          $stockStatus = $row[$i][5];
                          $partList = <<<HTML_CODE
<tr>
    <td class="mdl-data-table__cell--non-numeric text_left">$partNumber</td>
    <td class="mdl-data-table__cell--non-numeric text_left">$partName</td>
    <td class="mdl-data-table__cell--non-numeric text_left">$stockQuantity</td>
    <td class="mdl-data-table__cell--non-numeric text_left">$$stockPrice</td>
</tr>
HTML_CODE;
                          echo $partList;
                        }
                        ?>
                        </tbody>
                    </table>
                    <nav aria-label="Page navigation" style="margin-top: 24px">
                        <ul class="pagination justify-content-center">
                          <?php
                          $keyword = "";
                          if (strlen($search) > 0) {
                            $keyword = "q=$search&";
                          }
                          $totalPages = mysqli_num_rows($rs) / 5;
                          for ($i = 1; $i < $totalPages + 1; $i++) {
                            $pageHtml = <<<HTML_CODE
 <li class="page-item"><a class="page-link" href="{$_SERVER['PHP_SELF']}?{$keyword}page=$i">$i</a></li>
HTML_CODE;
                            echo $pageHtml;
                          }
                          ?>
                        </ul>
                    </nav>
                </div>
                <div class="modal-footer">
                    <!--          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>-->
                    <button id="btn_add" type="button" class="btn btn-primary" data-dismiss="modal" disabled="disabled">
                        Add
                    </button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
mysqli_free_result($rs);
mysqli_close($conn);
?>
<script src="./js/index.js"></script>
</body>

</html>