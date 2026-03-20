<?php
// ================================
// db.php
//
// Tämä tiedosto yhdistää sovelluksen tietokantaan.
// Siellä säilytetään
// käyttäjät, tehtävät ja kirjautumistapahtumat.
//
// Tämä tiedosto myös:
// - Lukee salasanat .env-tiedostosta eikä kirjoita
//   niitä suoraan koodiin
// - Luo tietokantataulut automaattisesti jos niitä
//   ei vielä ole olemassa
// - Huolehtii että vanhat lokimerkinnät siivotaan
//   pois silloin tällöin
// - Sisältää apufunktiot istunnon tarkistukseen
//   ja turvatokenien luontiin
// ================================