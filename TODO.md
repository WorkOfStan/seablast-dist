# TODO

All planned changes to this project are documented in this file.

## Demo

- 231221, use SB_GET_ARGUMENT_ID and SB_GET_ARGUMENT_CODE to demonstrate GET parameter usage
- 231229, adapt menu to display the links from README.md examples section
- 240112, /api/error using \Seablast\Seablast\Api\ApiErrorModel
- 241205, add some full Model with `public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)`
- 2512 - make-seablast-dist-your-own.sh or plant-the-seed.sh ... Které v bashi replaces Seablast/dist a workofstan tím, co tam má být - parametry ... A pak se sám smaže. Vyzkoušet na Marcury?
  - Ze souborů by mohlo vymazat bloky
  - // Seablast-dist example block
  - // /Seablast-dist example block
  - Hledá jen ten string na řádce, tak způsob komentování je fuk

## Security

- 231207, CSRF token
- 241205, add Seablast/Auth
