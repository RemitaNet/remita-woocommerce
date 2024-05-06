const settings = window.wc.wcSettings.getSetting( 'remita_data', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'Remita', 'wc-remita' );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};

const Block_Gateway = {
    name: 'remita',
    label: window.wp.element.createElement(() =>
    window.wp.element.createElement(
      "span",
      null,
      "  " + settings.title + ' ',
      window.wp.element.createElement("img", {
        src: settings.icons,
        alt: settings.title,
        style: { float: 'right', marginRight: '20px', paddingLeft: '20px' }
      }),
    )
  ),



    // label: Object( window.wp.element.createElement )( Label, null ),
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );