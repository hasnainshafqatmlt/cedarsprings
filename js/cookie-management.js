// https://www.w3schools.com/js/js_cookies.asp

function setCookie(cname, cvalue, exdays = 1) {
  const d = new Date();
  d.setTime(d.getTime() + exdays * 24 * 60 * 60 * 1000);
  let expires = "expires=" + d.toUTCString();
  const paths = [
    "/camps/queue",
    "/camps-summer-queue-registration",
    "/camps/queue/submitcamperqueue",
    "/camps/queue/complete_registration",
  ];
  console.log("cname, cvalue", cname, cvalue);
  paths.forEach((path) => {
    document.cookie =
      cname + "=" + cvalue + ";" + expires + ";path=" + path + "; SameSite=Lax";
  });
}

function getCookie(cname) {
  let name = cname + "=";
  let ca = document.cookie.split(";");

  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == " ") {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}

function deleteCookie(cname) {
  console.log(" in deleteCookie", cname);
  const d = new Date();
  d.setTime(d.getTime() - 1000);
  let expires = "expires=" + d.toUTCString();
  const paths = [
    "/camps/queue",
    "/camps-summer-queue-registration",
    "/camps/queue/submitcamperqueue",
    "/camps/queue/complete_registration",
  ];
  paths.forEach((path) => {
    document.cookie = cname + "=;" + expires + ";path=" + path + "";
  });
}

// gives us a function to ensure that cookies work
function testCookieSupport() {
  setCookie("cookieSupport", "why hello there");

  const result = checkCookie("cookieSupport");
  if (result) {
    deleteCookie("cookieSupport");
  }

  return result;
}

function checkCookie(cname) {
  let q = getCookie(cname);
  if (q != "") {
    return true;
  } else {
    return false;
  }
}
