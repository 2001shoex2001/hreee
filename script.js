menu.onclick = function myFunction(){
    var x = documet.getElementById('myTopnav');
    
    if (x.className === "topnav") {
        x.className += " responsive";
    } else {
            x.className = "topnav";
        }
    }