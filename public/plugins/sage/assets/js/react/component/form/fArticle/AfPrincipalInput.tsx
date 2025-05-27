import * as React from "react";
import { useImperativeHandle } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../functions/translations";

let translations: any = getTranslations();

export type AcPrixVenInputState = {
  defaultCtNum: string;
  ctNum: number | string;
  onAfPrincipalChangedParent: Function;
};

export const AfPrincipalInput = React.forwardRef(
  (
    { defaultCtNum, ctNum, onAfPrincipalChangedParent }: AcPrixVenInputState,
    ref,
  ) => {
    const [selectedCtNum, setSelectedCtNum] =
      React.useState<string>(defaultCtNum);
    const name = `_sage_fArtfournisses[${ctNum}].afPrincipal`;

    useImperativeHandle(ref, () => ({
      onAfPrincipalChanged(newCtNum: string) {
        setSelectedCtNum(newCtNum);
      },
    }));

    return (
      <>
        <label htmlFor={name} style={{ display: "none" }}>
          <Tooltip title={name} arrow placement="top">
            <span>{translations.words.supplierRef}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "center" }}>
          <input
            id={name}
            name={name}
            type="checkbox"
            checked={selectedCtNum === ctNum}
            onChange={(e) => {
              if (e.target.checked) {
                onAfPrincipalChangedParent(name, ctNum);
              }
            }}
          />
        </div>
      </>
    );
  },
);
