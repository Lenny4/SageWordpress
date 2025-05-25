import * as React from "react";

const language = $("[data-sage-language]").attr("data-sage-language");
const currency = $("[data-sage-currency]").attr("data-sage-currency");

export type State = {
  price: number;
};

export const PriceComponent: React.FC<State> = ({ price }) => {
  const priceFormat = (
    price: number | undefined,
    locale: string,
    currencyDisplay: string = "symbol",
    hideCent: boolean = false,
  ) => {
    if (price !== undefined) {
      const config: any = {
        style: "currency",
        currency: currency,
        currencyDisplay: currencyDisplay,
      };
      if (hideCent) {
        config.minimumFractionDigits = 0;
        config.maximumFractionDigits = 0;
      }
      return new Intl.NumberFormat(locale, config).format(price);
    }
    return "";
  };

  return <>{priceFormat(price, language)}</>;
};
