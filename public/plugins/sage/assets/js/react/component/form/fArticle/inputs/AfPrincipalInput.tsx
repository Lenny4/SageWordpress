import * as React from "react";
import { useImperativeHandle } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";
import { TriggerFormContentChanged } from "../../../../../interface/InputInterface";

let translations: any = getTranslations();

export type AfPrincipalState = {
  selectedCtNum: string;
  ctNum: string;
  onAfPrincipalChangedParent: TriggerFormContentChanged;
};

export const AfPrincipalInput = React.forwardRef(
  (
    { selectedCtNum, ctNum, onAfPrincipalChangedParent }: AfPrincipalState,
    ref,
  ) => {
    const name = `_sage_fArtfournisses[${ctNum}].afPrincipal`;

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<boolean> {
        // todo
        return false;
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
