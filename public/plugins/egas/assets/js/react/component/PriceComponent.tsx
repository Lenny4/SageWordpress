import * as React from "react";
import {TOKEN} from "../../token";

const language = $(`[data-${TOKEN}-language]`).attr(`data-${TOKEN}-language`);
const currency = $(`[data-${TOKEN}-currency]`).attr(`data-${TOKEN}-currency`);

export type State = {
  price: number;
};

export const PriceComponent: React.FC<State> = ({price}) => {
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
